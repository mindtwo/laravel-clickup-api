<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhookDelivery;

uses()->group('webhook-model');

beforeEach(function () {
    // Run migrations
    foreach (File::allFiles(__DIR__.'/../../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }
});

test('webhook has deliveries relationship', function () {
    $webhook = createModelTestWebhook();

    // Create deliveries
    $webhook->deliveries()->create([
        'event'           => 'taskCreated',
        'payload'         => ['test' => 'data'],
        'status'          => 'processed',
        'idempotency_key' => 'key1',
    ]);

    $webhook->deliveries()->create([
        'event'           => 'taskUpdated',
        'payload'         => ['test' => 'data2'],
        'status'          => 'processed',
        'idempotency_key' => 'key2',
    ]);

    expect($webhook->deliveries)->toHaveCount(2);
    expect($webhook->deliveries->first())->toBeInstanceOf(ClickUpWebhookDelivery::class);
});

test('webhook casts health status to enum', function () {
    $webhook = createModelTestWebhook(['health_status' => 'active']);

    expect($webhook->health_status)->toBeInstanceOf(WebhookHealthStatus::class);
    expect($webhook->health_status)->toBe(WebhookHealthStatus::ACTIVE);
});

test('webhook casts dates correctly', function () {
    $webhook = createModelTestWebhook([
        'last_triggered_at' => '2025-01-01 12:00:00',
        'health_checked_at' => '2025-01-02 12:00:00',
    ]);

    expect($webhook->last_triggered_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($webhook->health_checked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('webhook casts last error to array', function () {
    $error = ['error' => 'Test error', 'timestamp' => '2025-01-01T12:00:00'];
    $webhook = createModelTestWebhook(['last_error' => $error]);

    expect($webhook->last_error)->toBeArray();
    expect($webhook->last_error)->toBe($error);
});

test('record delivery increments counter and updates timestamp', function () {
    $webhook = createModelTestWebhook(['total_deliveries' => 5]);

    $webhook->recordDelivery();

    $webhook->refresh();
    expect($webhook->total_deliveries)->toBe(6);
    expect($webhook->last_triggered_at)->not->toBeNull();
});

test('record failure increments counter and stores error', function () {
    $webhook = createModelTestWebhook(['failed_deliveries' => 2]);

    $webhook->recordFailure('Test error message');

    $webhook->refresh();
    expect($webhook->failed_deliveries)->toBe(3);
    expect($webhook->last_error)->toBeArray();
    expect($webhook->last_error['error'])->toBe('Test error message');
    expect($webhook->last_error)->toHaveKey('timestamp');
});

test('failure rate attribute calculates correctly', function () {
    $webhook = createModelTestWebhook([
        'total_deliveries'  => 100,
        'failed_deliveries' => 25,
    ]);

    expect($webhook->failure_rate)->toBe(25.0);
});

test('failure rate returns zero for no deliveries', function () {
    $webhook = createModelTestWebhook([
        'total_deliveries'  => 0,
        'failed_deliveries' => 0,
    ]);

    expect($webhook->failure_rate)->toBe(0.0);
});

test('is healthy returns true for active status', function () {
    $webhook = createModelTestWebhook(['health_status' => WebhookHealthStatus::ACTIVE]);

    expect($webhook->isHealthy())->toBeTrue();
});

test('is healthy returns false for failing status', function () {
    $webhook = createModelTestWebhook(['health_status' => WebhookHealthStatus::FAILING]);

    expect($webhook->isHealthy())->toBeFalse();
});

test('is healthy returns false for suspended status', function () {
    $webhook = createModelTestWebhook(['health_status' => WebhookHealthStatus::SUSPENDED]);

    expect($webhook->isHealthy())->toBeFalse();
});

test('healthy scope filters active webhooks', function () {
    createModelTestWebhook(['clickup_webhook_id' => 'wh_1', 'health_status' => WebhookHealthStatus::ACTIVE]);
    createModelTestWebhook(['clickup_webhook_id' => 'wh_2', 'health_status' => WebhookHealthStatus::FAILING]);
    createModelTestWebhook(['clickup_webhook_id' => 'wh_3', 'health_status' => WebhookHealthStatus::SUSPENDED]);

    $healthyWebhooks = ClickUpWebhook::healthy()->get();

    expect($healthyWebhooks)->toHaveCount(1);
    expect($healthyWebhooks->first()->clickup_webhook_id)->toBe('wh_1');
});

test('failing scope filters failing webhooks', function () {
    createModelTestWebhook(['clickup_webhook_id' => 'wh_1', 'health_status' => WebhookHealthStatus::ACTIVE]);
    createModelTestWebhook(['clickup_webhook_id' => 'wh_2', 'health_status' => WebhookHealthStatus::FAILING]);
    createModelTestWebhook(['clickup_webhook_id' => 'wh_3', 'health_status' => WebhookHealthStatus::FAILING]);

    $failingWebhooks = ClickUpWebhook::failing()->get();

    expect($failingWebhooks)->toHaveCount(2);
});

test('suspended scope filters suspended webhooks', function () {
    createModelTestWebhook(['clickup_webhook_id' => 'wh_1', 'health_status' => WebhookHealthStatus::ACTIVE]);
    createModelTestWebhook(['clickup_webhook_id' => 'wh_2', 'health_status' => WebhookHealthStatus::SUSPENDED]);
    createModelTestWebhook(['clickup_webhook_id' => 'wh_3', 'health_status' => WebhookHealthStatus::FAILING]);

    $suspendedWebhooks = ClickUpWebhook::suspended()->get();

    expect($suspendedWebhooks)->toHaveCount(1);
    expect($suspendedWebhooks->first()->clickup_webhook_id)->toBe('wh_2');
});

test('needs recovery scope filters correctly', function () {
    createModelTestWebhook([
        'clickup_webhook_id' => 'wh_1',
        'health_status'      => WebhookHealthStatus::ACTIVE,
        'is_active'          => true,
    ]);
    createModelTestWebhook([
        'clickup_webhook_id' => 'wh_2',
        'health_status'      => WebhookHealthStatus::FAILING,
        'is_active'          => false,
    ]);
    createModelTestWebhook([
        'clickup_webhook_id' => 'wh_3',
        'health_status'      => WebhookHealthStatus::SUSPENDED,
        'is_active'          => false,
    ]);
    createModelTestWebhook([
        'clickup_webhook_id' => 'wh_4',
        'health_status'      => WebhookHealthStatus::FAILING,
        'is_active'          => true, // Still active, doesn't need recovery
    ]);

    $needsRecovery = ClickUpWebhook::needsRecovery()->get();

    expect($needsRecovery)->toHaveCount(2);
    expect($needsRecovery->contains('clickup_webhook_id', 'wh_2'))->toBeTrue();
    expect($needsRecovery->contains('clickup_webhook_id', 'wh_3'))->toBeTrue();
});

test('update health status sets active with no recent deliveries', function () {
    $webhook = createModelTestWebhook(['health_status' => WebhookHealthStatus::FAILING]);

    $webhook->updateHealthStatus();

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::ACTIVE);
});

test('update health status sets failing with high failure rate', function () {
    $webhook = createModelTestWebhook(['health_status' => WebhookHealthStatus::ACTIVE]);

    // Create 6 failed deliveries and 4 successful (60% failure rate)
    for ($i = 0; $i < 6; $i++) {
        $webhook->deliveries()->create([
            'event'           => 'taskCreated',
            'payload'         => ['test' => 'data'],
            'status'          => 'failed',
            'idempotency_key' => 'fail_'.$i,
            'created_at'      => now(),
        ]);
    }

    for ($i = 0; $i < 4; $i++) {
        $webhook->deliveries()->create([
            'event'           => 'taskCreated',
            'payload'         => ['test' => 'data'],
            'status'          => 'processed',
            'idempotency_key' => 'success_'.$i,
            'created_at'      => now(),
        ]);
    }

    $webhook->updateHealthStatus();

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::FAILING);
});

test('update health status ignores old deliveries', function () {
    $webhook = createModelTestWebhook(['health_status' => WebhookHealthStatus::ACTIVE]);

    // Create old failed deliveries (beyond 24 hours)
    for ($i = 0; $i < 10; $i++) {
        $webhook->deliveries()->create([
            'event'           => 'taskCreated',
            'payload'         => ['test' => 'data'],
            'status'          => 'failed',
            'idempotency_key' => 'old_fail_'.$i,
            'created_at'      => now()->subHours(25),
        ]);
    }

    // Create recent successful delivery
    $webhook->deliveries()->create([
        'event'           => 'taskCreated',
        'payload'         => ['test' => 'data'],
        'status'          => 'processed',
        'idempotency_key' => 'recent_success',
        'created_at'      => now(),
    ]);

    $webhook->updateHealthStatus();

    $webhook->refresh();
    // Should be ACTIVE because old failures are ignored
    expect($webhook->health_status)->toBe(WebhookHealthStatus::ACTIVE);
});

test('soft deletes work correctly', function () {
    $webhook = createModelTestWebhook(['clickup_webhook_id' => 'wh_test']);

    $webhook->delete();

    $this->assertSoftDeleted('clickup_webhooks', [
        'clickup_webhook_id' => 'wh_test',
    ]);

    expect(ClickUpWebhook::all())->toHaveCount(0);
    expect(ClickUpWebhook::withTrashed()->get())->toHaveCount(1);
});

test('webhook has correct table name', function () {
    $webhook = new ClickUpWebhook;

    expect($webhook->getTable())->toBe('clickup_webhooks');
});

/**
 * Create a test webhook in the database.
 */
function createModelTestWebhook(array $overrides = []): ClickUpWebhook
{
    return ClickUpWebhook::create(array_merge([
        'clickup_webhook_id' => 'wh_test_'.uniqid(),
        'endpoint'           => 'https://test.local/webhooks/clickup',
        'event'              => '*',
        'target_type'        => 'workspace',
        'target_id'          => 'workspace_123',
        'secret'             => 'test-secret',
        'is_active'          => true,
        'health_status'      => WebhookHealthStatus::ACTIVE,
        'total_deliveries'   => 0,
        'failed_deliveries'  => 0,
        'fail_count'         => 0,
    ], $overrides));
}
