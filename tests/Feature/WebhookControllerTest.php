<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskCreated;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskUpdated;
use Mindtwo\LaravelClickUpApi\Http\Middleware\VerifyClickUpWebhookSignature;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhookDelivery;

uses()->group('webhook-controller');

beforeEach(function () {
    // Run migrations
    foreach (File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }

    // Disable signature verification middleware for controller tests
    // (signature verification has its own dedicated test suite)
    $this->withoutMiddleware(VerifyClickUpWebhookSignature::class);
});

test('webhook handles task created event', function () {
    Event::fake([TaskCreated::class]);

    $webhook = createControllerTestWebhook();

    $payload = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

    $response = $this->postJson('/webhooks/clickup', $payload);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    Event::assertDispatched(TaskCreated::class);
});

test('webhook handles task updated event', function () {
    Event::fake([TaskUpdated::class]);

    $webhook = createControllerTestWebhook();

    $payload = createControllerTestWebhookPayload('taskUpdated', $webhook->clickup_webhook_id);

    $response = $this->postJson('/webhooks/clickup', $payload);

    $response->assertStatus(200);
    Event::assertDispatched(TaskUpdated::class);
});

test('webhook detects duplicate deliveries', function () {
    Event::fake();

    $webhook = createControllerTestWebhook();
    $payload = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

    // First delivery should succeed
    $response1 = $this->postJson('/webhooks/clickup', $payload);
    $response1->assertStatus(200);
    $response1->assertJson(['status' => 'success']);

    // Second delivery with same payload should be detected as duplicate
    $response2 = $this->postJson('/webhooks/clickup', $payload);
    $response2->assertStatus(200);
    $response2->assertJson(['status' => 'duplicate']);

    // Verify only one delivery was recorded
    expect(ClickUpWebhookDelivery::all())->toHaveCount(1);
});

test('webhook records delivery in database', function () {
    $webhook = createControllerTestWebhook();
    $payload = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

    $this->postJson('/webhooks/clickup', $payload);

    $this->assertDatabaseHas('clickup_webhook_deliveries', [
        'clickup_webhook_id' => $webhook->id,
        'event'              => 'taskCreated',
        'status'             => 'processed',
    ]);

    $delivery = ClickUpWebhookDelivery::first();
    expect($delivery)->not->toBeNull();
    expect($delivery->event)->toBe('taskCreated');
    expect($delivery->status)->toBe('processed');
    expect($delivery->processing_time_ms)->toBeInt();
});

test('webhook updates delivery counters', function () {
    Event::fake();

    $webhook = createControllerTestWebhook();

    // First delivery
    $payload1 = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id, 'history-1');
    $this->postJson('/webhooks/clickup', $payload1);

    $webhook->refresh();
    expect($webhook->total_deliveries)->toBe(1);
    expect($webhook->failed_deliveries)->toBe(0);
    expect($webhook->last_triggered_at)->not->toBeNull();

    // Second delivery
    $payload2 = createControllerTestWebhookPayload('taskUpdated', $webhook->clickup_webhook_id, 'history-2');
    $this->postJson('/webhooks/clickup', $payload2);

    $webhook->refresh();
    expect($webhook->total_deliveries)->toBe(2);
    expect($webhook->failed_deliveries)->toBe(0);
});

test('webhook returns 404 for inactive webhook', function () {
    $webhook = createControllerTestWebhook(['is_active' => false]);

    $payload = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

    $response = $this->postJson('/webhooks/clickup', $payload);

    $response->assertStatus(404);
    $response->assertJson(['status' => 'webhook_not_found']);
});

test('webhook returns 404 for unknown webhook id', function () {
    $payload = createControllerTestWebhookPayload('taskCreated', 'unknown-webhook-id');

    $response = $this->postJson('/webhooks/clickup', $payload);

    $response->assertStatus(404);
    $response->assertJson(['status' => 'webhook_not_found']);
});

test('webhook handles processing errors gracefully', function () {
    // Force an error by registering a listener that throws
    Event::listen(TaskCreated::class, function () {
        throw new Exception('Test error');
    });

    $webhook = createControllerTestWebhook();
    $payload = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

    $response = $this->postJson('/webhooks/clickup', $payload);

    $response->assertStatus(500);
    $response->assertJsonStructure(['status', 'message']);

    // Verify delivery was recorded as failed
    $delivery = ClickUpWebhookDelivery::first();
    expect($delivery)->not->toBeNull();
    expect($delivery->status)->toBe('failed');
    expect($delivery->error_message)->not->toBeNull();

    // Verify webhook failure was recorded
    $webhook->refresh();
    expect($webhook->failed_deliveries)->toBe(1);
    expect($webhook->last_error)->not->toBeNull();
});

test('webhook creates idempotency key from webhook and history id', function () {
    Event::fake();

    $webhook = createControllerTestWebhook();
    $historyId = 'test-history-123';
    $payload = createControllerTestWebhookPayload('taskCreated', $webhook->clickup_webhook_id, $historyId);

    $this->postJson('/webhooks/clickup', $payload);

    $expectedKey = $webhook->clickup_webhook_id.':'.$historyId;

    $this->assertDatabaseHas('clickup_webhook_deliveries', [
        'idempotency_key' => $expectedKey,
    ]);
});

test('webhook handles unknown event types', function () {
    Event::fake();

    $webhook = createControllerTestWebhook();
    $payload = createControllerTestWebhookPayload('unknownEventType', $webhook->clickup_webhook_id);

    $response = $this->postJson('/webhooks/clickup', $payload);

    // Should still process successfully but not dispatch any event
    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    // Verify delivery was recorded as processed
    $delivery = ClickUpWebhookDelivery::first();
    expect($delivery->status)->toBe('processed');
});

/**
 * Create a test webhook in the database.
 */
function createControllerTestWebhook(array $overrides = []): ClickUpWebhook
{
    return ClickUpWebhook::create(array_merge([
        'clickup_webhook_id' => 'wh_test_'.uniqid(),
        'endpoint'           => 'https://test.local/webhooks/clickup',
        'event'              => '*',
        'target_type'        => 'workspace',
        'target_id'          => 'workspace_123',
        'secret'             => 'test-secret',
        'is_active'          => true,
        'health_status'      => 'active',
    ], $overrides));
}

/**
 * Create a webhook payload for testing.
 */
function createControllerTestWebhookPayload(string $event, string $webhookId, ?string $historyId = null): array
{
    return [
        'webhook_id'    => $webhookId,
        'event'         => $event,
        'task_id'       => 'task_123',
        'history_items' => [
            [
                'id'   => $historyId ?? 'history_'.uniqid(),
                'type' => 1,
                'date' => '1234567890',
            ],
        ],
    ];
}
