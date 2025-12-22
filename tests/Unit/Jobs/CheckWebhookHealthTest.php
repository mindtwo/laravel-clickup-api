<?php

declare(strict_types=1);

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;
use Mindtwo\LaravelClickUpApi\Jobs\CheckWebhookHealth;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;

uses()->group('webhook-health-job');

beforeEach(function () {
    // Run migrations
    foreach (File::allFiles(__DIR__.'/../../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }

    // Set workspace ID for testing
    config(['clickup-api.default_workspace_id' => 'workspace_123']);
});

afterEach(function () {
    Mockery::close();
});

test('job syncs webhook health status from api', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'active',
            'fail_count' => 0,
        ],
    ]);

    Log::shouldReceive('info')->twice(); // Starting check + completion
    Log::shouldReceive('debug')->once(); // Fetched webhooks

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::ACTIVE);
    expect($webhook->fail_count)->toBe(0);
    expect($webhook->health_checked_at)->not->toBeNull();
});

test('job updates webhook to failing status', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'failing',
            'fail_count' => 45,
        ],
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->once();
    Log::shouldReceive('warning')->times(2); // Status change + auto-disable

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::FAILING);
    expect($webhook->fail_count)->toBe(45);
    expect($webhook->is_active)->toBeFalse(); // Should be auto-disabled
});

test('job updates webhook to suspended status', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'suspended',
            'fail_count' => 100,
        ],
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->once();
    Log::shouldReceive('warning')->times(2);

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::SUSPENDED);
    expect($webhook->fail_count)->toBe(100);
    expect($webhook->is_active)->toBeFalse();
});

test('job tracks fail count changes', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    // First check - some failures
    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'active',
            'fail_count' => 10,
        ],
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->once();

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook->refresh();
    expect($webhook->fail_count)->toBe(10);
    expect($webhook->is_active)->toBeTrue(); // Still active

    // Second check - more failures but still active
    $webhooksEndpoint2 = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'active',
            'fail_count' => 25,
        ],
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->once();

    $job2 = new CheckWebhookHealth;
    $job2->handle($webhooksEndpoint2);

    $webhook->refresh();
    expect($webhook->fail_count)->toBe(25);
    expect($webhook->is_active)->toBeTrue();
});

test('job handles multiple webhooks', function () {
    $webhook1 = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);
    $webhook2 = createHealthTestWebhook('wh_456', WebhookHealthStatus::ACTIVE);

    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'active',
            'fail_count' => 0,
        ],
        [
            'id'         => 'wh_456',
            'status'     => 'failing',
            'fail_count' => 50,
        ],
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->once();
    Log::shouldReceive('warning')->times(2); // For webhook2 status change

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook1->refresh();
    $webhook2->refresh();

    expect($webhook1->health_status)->toBe(WebhookHealthStatus::ACTIVE);
    expect($webhook1->is_active)->toBeTrue();

    expect($webhook2->health_status)->toBe(WebhookHealthStatus::FAILING);
    expect($webhook2->is_active)->toBeFalse(); // Auto-disabled
});

test('job skips webhooks not in database', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    // API returns webhook that doesn't exist in our database
    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'active',
            'fail_count' => 0,
        ],
        [
            'id'         => 'wh_unknown',
            'status'     => 'active',
            'fail_count' => 0,
        ],
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->once();

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    // Should only update wh_123
    expect(ClickUpWebhook::all())->toHaveCount(1);

    $webhook->refresh();
    expect($webhook->health_checked_at)->not->toBeNull();
});

test('job handles missing workspace id gracefully', function () {
    config(['clickup-api.default_workspace_id' => null]);

    Log::shouldReceive('info')
        ->once()
        ->with('No workspace found for health check');

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    // Should not call index() method

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    // Test passes if no exception is thrown
    expect(true)->toBeTrue();
});

test('job handles api errors gracefully', function () {
    createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    $response = Mockery::mock(LazyResponseProxy::class);
    $response->shouldReceive('status')->andReturn(500);

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    $webhooksEndpoint->shouldReceive('index')
        ->with('workspace_123')
        ->once()
        ->andReturn($response);

    Log::shouldReceive('info')->twice(); // Starting check + completion
    Log::shouldReceive('warning')->once(); // Failed API call

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    // Webhook should not be updated
    $webhook = ClickUpWebhook::first();
    expect($webhook->health_checked_at)->toBeNull();
});

test('job logs status changes', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'         => 'wh_123',
            'status'     => 'failing',
            'fail_count' => 45,
        ],
    ]);

    Log::shouldReceive('info')->twice(); // Start + completion
    Log::shouldReceive('debug')->once(); // Fetched webhooks
    Log::shouldReceive('warning')
        ->once()
        ->with('ClickUp webhook health status changed', Mockery::on(function ($arg) {
            return $arg['webhook_id'] === 'wh_123'
                && $arg['previous_status'] === 'active'
                && $arg['new_status'] === 'failing'
                && $arg['fail_count'] === 45;
        }));
    Log::shouldReceive('warning')
        ->once()
        ->with('Webhook auto-disabled due to health status', Mockery::any());

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);
});

test('job handles webhooks with default status', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    // API response without explicit status (should default to 'active')
    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id' => 'wh_123',
            // No 'status' field
            'fail_count' => 0,
        ],
    ]);

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::ACTIVE);
});

test('job handles webhooks with missing fail count', function () {
    $webhook = createHealthTestWebhook('wh_123', WebhookHealthStatus::ACTIVE);

    // API response without fail_count (should default to 0)
    $webhooksEndpoint = mockHealthTestWebhooksEndpoint([
        [
            'id'     => 'wh_123',
            'status' => 'active',
            // No 'fail_count' field
        ],
    ]);

    $job = new CheckWebhookHealth;
    $job->handle($webhooksEndpoint);

    $webhook->refresh();
    expect($webhook->fail_count)->toBe(0);
});

/**
 * Create a test webhook in the database.
 */
function createHealthTestWebhook(string $webhookId, WebhookHealthStatus $status): ClickUpWebhook
{
    return ClickUpWebhook::create([
        'clickup_webhook_id' => $webhookId,
        'endpoint'           => 'https://test.local/webhooks/clickup',
        'event'              => '*',
        'target_type'        => 'workspace',
        'target_id'          => 'workspace_123',
        'secret'             => 'test-secret',
        'is_active'          => true,
        'health_status'      => $status,
    ]);
}

/**
 * Mock the Webhooks endpoint to return a specific response.
 */
function mockHealthTestWebhooksEndpoint(array $webhooks): Webhooks
{
    $response = Mockery::mock(LazyResponseProxy::class);
    $response->shouldReceive('status')->andReturn(200);
    $response->shouldReceive('json')->andReturn(['webhooks' => $webhooks]);

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    $webhooksEndpoint->shouldReceive('index')
        ->with('workspace_123')
        ->once()
        ->andReturn($response);

    return $webhooksEndpoint;
}
