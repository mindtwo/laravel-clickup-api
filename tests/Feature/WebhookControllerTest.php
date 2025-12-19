<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Events\TaskCreated;
use Mindtwo\LaravelClickUpApi\Events\TaskUpdated;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhookDelivery;
use Mindtwo\LaravelClickUpApi\Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        foreach (File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    public function test_webhook_handles_task_created_event(): void
    {
        Event::fake([TaskCreated::class]);

        $webhook = $this->createWebhook();

        $payload = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

        $response = $this->postJson('/webhooks/clickup', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        Event::assertDispatched(TaskCreated::class);
    }

    public function test_webhook_handles_task_updated_event(): void
    {
        Event::fake([TaskUpdated::class]);

        $webhook = $this->createWebhook();

        $payload = $this->createWebhookPayload('taskUpdated', $webhook->clickup_webhook_id);

        $response = $this->postJson('/webhooks/clickup', $payload);

        $response->assertStatus(200);
        Event::assertDispatched(TaskUpdated::class);
    }

    public function test_webhook_detects_duplicate_deliveries(): void
    {
        Event::fake();

        $webhook = $this->createWebhook();
        $payload = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

        // First delivery should succeed
        $response1 = $this->postJson('/webhooks/clickup', $payload);
        $response1->assertStatus(200);
        $response1->assertJson(['status' => 'success']);

        // Second delivery with same payload should be detected as duplicate
        $response2 = $this->postJson('/webhooks/clickup', $payload);
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'duplicate']);

        // Verify only one delivery was recorded
        $this->assertCount(1, ClickUpWebhookDelivery::all());
    }

    public function test_webhook_records_delivery_in_database(): void
    {
        Event::fake();

        $webhook = $this->createWebhook();
        $payload = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

        $this->postJson('/webhooks/clickup', $payload);

        $this->assertDatabaseHas('clickup_webhook_deliveries', [
            'clickup_webhook_id' => $webhook->id,
            'event'              => 'taskCreated',
            'status'             => 'processed',
        ]);

        $delivery = ClickUpWebhookDelivery::first();
        $this->assertNotNull($delivery);
        $this->assertEquals('taskCreated', $delivery->event);
        $this->assertEquals('processed', $delivery->status);
        $this->assertIsInt($delivery->processing_time_ms);
        $this->assertGreaterThan(0, $delivery->processing_time_ms);
    }

    public function test_webhook_updates_delivery_counters(): void
    {
        Event::fake();

        $webhook = $this->createWebhook();

        // First delivery
        $payload1 = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id, 'history-1');
        $this->postJson('/webhooks/clickup', $payload1);

        $webhook->refresh();
        $this->assertEquals(1, $webhook->total_deliveries);
        $this->assertEquals(0, $webhook->failed_deliveries);
        $this->assertNotNull($webhook->last_triggered_at);

        // Second delivery
        $payload2 = $this->createWebhookPayload('taskUpdated', $webhook->clickup_webhook_id, 'history-2');
        $this->postJson('/webhooks/clickup', $payload2);

        $webhook->refresh();
        $this->assertEquals(2, $webhook->total_deliveries);
        $this->assertEquals(0, $webhook->failed_deliveries);
    }

    public function test_webhook_returns_404_for_inactive_webhook(): void
    {
        $webhook = $this->createWebhook(['is_active' => false]);

        $payload = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

        $response = $this->postJson('/webhooks/clickup', $payload);

        $response->assertStatus(404);
        $response->assertJson(['status' => 'webhook_not_found']);
    }

    public function test_webhook_returns_404_for_unknown_webhook_id(): void
    {
        $payload = $this->createWebhookPayload('taskCreated', 'unknown-webhook-id');

        $response = $this->postJson('/webhooks/clickup', $payload);

        $response->assertStatus(404);
        $response->assertJson(['status' => 'webhook_not_found']);
    }

    public function test_webhook_handles_processing_errors_gracefully(): void
    {
        Event::fake();

        // Force an error by dispatching to a malformed event
        Event::shouldReceive('dispatch')
            ->once()
            ->andThrow(new \Exception('Test error'));

        $webhook = $this->createWebhook();
        $payload = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id);

        $response = $this->postJson('/webhooks/clickup', $payload);

        $response->assertStatus(500);
        $response->assertJsonStructure(['status', 'message']);

        // Verify delivery was recorded as failed
        $delivery = ClickUpWebhookDelivery::first();
        $this->assertNotNull($delivery);
        $this->assertEquals('failed', $delivery->status);
        $this->assertNotNull($delivery->error_message);

        // Verify webhook failure was recorded
        $webhook->refresh();
        $this->assertEquals(1, $webhook->failed_deliveries);
        $this->assertNotNull($webhook->last_error);
    }

    public function test_webhook_creates_idempotency_key_from_webhook_and_history_id(): void
    {
        Event::fake();

        $webhook = $this->createWebhook();
        $historyId = 'test-history-123';
        $payload = $this->createWebhookPayload('taskCreated', $webhook->clickup_webhook_id, $historyId);

        $this->postJson('/webhooks/clickup', $payload);

        $expectedKey = $webhook->clickup_webhook_id.':'.$historyId;

        $this->assertDatabaseHas('clickup_webhook_deliveries', [
            'idempotency_key' => $expectedKey,
        ]);
    }

    public function test_webhook_handles_unknown_event_types(): void
    {
        Event::fake();

        $webhook = $this->createWebhook();
        $payload = $this->createWebhookPayload('unknownEventType', $webhook->clickup_webhook_id);

        $response = $this->postJson('/webhooks/clickup', $payload);

        // Should still process successfully but not dispatch any event
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Verify delivery was recorded as processed
        $delivery = ClickUpWebhookDelivery::first();
        $this->assertEquals('processed', $delivery->status);
    }

    /**
     * Create a test webhook in the database.
     */
    protected function createWebhook(array $overrides = []): ClickUpWebhook
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
    protected function createWebhookPayload(string $event, string $webhookId, ?string $historyId = null): array
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
}
