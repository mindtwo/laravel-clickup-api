<?php

declare(strict_types=1);

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mockery;

uses()->group('recover-webhook-command');

beforeEach(function () {
    // Run migrations
    foreach (File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }
});

afterEach(function () {
    Mockery::close();
});

test('command requires webhook id or all flag', function () {
    $this->artisan('clickup:webhook-recover')
        ->expectsOutput('Please provide either a webhook_id or use the --all flag')
        ->assertExitCode(2); // INVALID
});

test('command recovers single webhook by id', function () {
    $webhook = createCommandTestFailingWebhook('wh_123');

    $webhooksEndpoint = mockCommandTestSuccessfulRecovery($webhook->clickup_webhook_id);
    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    Log::shouldReceive('info')->once();

    $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
        ->expectsOutput('Attempting to recover webhook: wh_123')
        ->expectsOutput('  Status: failing')
        ->expectsOutput('  ✓ Successfully recovered webhook wh_123')
        ->assertExitCode(0); // SUCCESS

    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::ACTIVE);
    expect($webhook->is_active)->toBeTrue();
    expect($webhook->fail_count)->toBe(0);
});

test('command returns error for unknown webhook id', function () {
    $this->artisan('clickup:webhook-recover', ['webhook_id' => 'unknown'])
        ->expectsOutput('Webhook not found: unknown')
        ->assertExitCode(1); // FAILURE
});

test('command recovers all failing webhooks', function () {
    $webhook1 = createCommandTestFailingWebhook('wh_123');
    $webhook2 = createCommandTestSuspendedWebhook('wh_456');
    createCommandTestActiveWebhook('wh_789'); // Should not be recovered

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    $webhooksEndpoint->shouldReceive('update')
        ->twice()
        ->andReturn(mockCommandTestSuccessResponse());

    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    Log::shouldReceive('info')->times(2);

    $this->artisan('clickup:webhook-recover', ['--all' => true])
        ->expectsOutput('Found 2 webhook(s) to recover.')
        ->expectsOutput('Recovery complete: 2 successful, 0 failed')
        ->assertExitCode(0);

    $webhook1->refresh();
    $webhook2->refresh();

    expect($webhook1->health_status)->toBe(WebhookHealthStatus::ACTIVE);
    expect($webhook2->health_status)->toBe(WebhookHealthStatus::ACTIVE);
    expect($webhook1->is_active)->toBeTrue();
    expect($webhook2->is_active)->toBeTrue();
});

test('command handles no webhooks needing recovery', function () {
    createCommandTestActiveWebhook('wh_123');

    $this->artisan('clickup:webhook-recover', ['--all' => true])
        ->expectsOutput('No webhooks need recovery.')
        ->assertExitCode(0);
});

test('command handles api errors gracefully', function () {
    $webhook = createCommandTestFailingWebhook('wh_123');

    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(400);
    $response->shouldReceive('json')->andReturn(['err' => 'Invalid webhook data']);

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    $webhooksEndpoint->shouldReceive('update')
        ->once()
        ->with('wh_123', Mockery::any())
        ->andReturn($response);

    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
        ->expectsOutput('  ✗ Failed to recover webhook: Invalid webhook data')
        ->assertExitCode(1);

    // Webhook should not be updated
    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::FAILING);
    expect($webhook->is_active)->toBeFalse();
});

test('command handles exceptions gracefully', function () {
    $webhook = createCommandTestFailingWebhook('wh_123');

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    $webhooksEndpoint->shouldReceive('update')
        ->once()
        ->andThrow(new \Exception('Network error'));

    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    Log::shouldReceive('error')->once();

    $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
        ->expectsOutput('  ✗ Error recovering webhook: Network error')
        ->assertExitCode(1);

    // Webhook should not be updated
    $webhook->refresh();
    expect($webhook->health_status)->toBe(WebhookHealthStatus::FAILING);
});

test('command resets fail count on recovery', function () {
    $webhook = createCommandTestWebhook([
        'clickup_webhook_id' => 'wh_123',
        'health_status'      => WebhookHealthStatus::FAILING,
        'is_active'          => false,
        'fail_count'         => 75,
    ]);

    $webhooksEndpoint = mockCommandTestSuccessfulRecovery('wh_123');
    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    Log::shouldReceive('info')->once();

    $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
        ->assertExitCode(0);

    $webhook->refresh();
    expect($webhook->fail_count)->toBe(0);
});

test('command recovers all with mixed results', function () {
    $webhook1 = createCommandTestFailingWebhook('wh_success');
    $webhook2 = createCommandTestFailingWebhook('wh_failure');

    $webhooksEndpoint = Mockery::mock(Webhooks::class);

    // First webhook succeeds
    $webhooksEndpoint->shouldReceive('update')
        ->once()
        ->with('wh_success', Mockery::any())
        ->andReturn(mockCommandTestSuccessResponse());

    // Second webhook fails
    $failureResponse = Mockery::mock(Response::class);
    $failureResponse->shouldReceive('status')->andReturn(400);
    $failureResponse->shouldReceive('json')->andReturn(['err' => 'Failed']);

    $webhooksEndpoint->shouldReceive('update')
        ->once()
        ->with('wh_failure', Mockery::any())
        ->andReturn($failureResponse);

    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    Log::shouldReceive('info')->once(); // For successful recovery

    $this->artisan('clickup:webhook-recover', ['--all' => true])
        ->expectsOutput('Found 2 webhook(s) to recover.')
        ->expectsOutput('Recovery complete: 1 successful, 1 failed')
        ->assertExitCode(1); // FAILURE because at least one failed

    $webhook1->refresh();
    $webhook2->refresh();

    expect($webhook1->health_status)->toBe(WebhookHealthStatus::ACTIVE);
    expect($webhook2->health_status)->toBe(WebhookHealthStatus::FAILING);
});

test('command displays webhook details', function () {
    $webhook = createCommandTestWebhook([
        'clickup_webhook_id' => 'wh_123',
        'endpoint'           => 'https://example.com/webhook',
        'health_status'      => WebhookHealthStatus::SUSPENDED,
        'is_active'          => false,
        'fail_count'         => 100,
    ]);

    $webhooksEndpoint = mockCommandTestSuccessfulRecovery('wh_123');
    $this->app->instance(Webhooks::class, $webhooksEndpoint);

    Log::shouldReceive('info')->once();

    $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
        ->expectsOutput('Attempting to recover webhook: wh_123')
        ->expectsOutput('  Status: suspended')
        ->expectsOutput('  Endpoint: https://example.com/webhook')
        ->expectsOutput('  Fail count: 100')
        ->assertExitCode(0);
});

/**
 * Create a failing webhook.
 */
function createCommandTestFailingWebhook(string $webhookId): ClickUpWebhook
{
    return createCommandTestWebhook([
        'clickup_webhook_id' => $webhookId,
        'health_status'      => WebhookHealthStatus::FAILING,
        'is_active'          => false,
        'fail_count'         => 50,
    ]);
}

/**
 * Create a suspended webhook.
 */
function createCommandTestSuspendedWebhook(string $webhookId): ClickUpWebhook
{
    return createCommandTestWebhook([
        'clickup_webhook_id' => $webhookId,
        'health_status'      => WebhookHealthStatus::SUSPENDED,
        'is_active'          => false,
        'fail_count'         => 100,
    ]);
}

/**
 * Create an active webhook.
 */
function createCommandTestActiveWebhook(string $webhookId): ClickUpWebhook
{
    return createCommandTestWebhook([
        'clickup_webhook_id' => $webhookId,
        'health_status'      => WebhookHealthStatus::ACTIVE,
        'is_active'          => true,
        'fail_count'         => 0,
    ]);
}

/**
 * Create a test webhook in the database.
 */
function createCommandTestWebhook(array $attributes): ClickUpWebhook
{
    return ClickUpWebhook::create(array_merge([
        'endpoint'    => 'https://test.local/webhooks/clickup',
        'event'       => '*',
        'target_type' => 'workspace',
        'target_id'   => 'workspace_123',
        'secret'      => 'test-secret',
    ], $attributes));
}

/**
 * Mock a successful recovery API response.
 */
function mockCommandTestSuccessfulRecovery(string $webhookId): Webhooks
{
    $response = mockCommandTestSuccessResponse();

    $webhooksEndpoint = Mockery::mock(Webhooks::class);
    $webhooksEndpoint->shouldReceive('update')
        ->once()
        ->with($webhookId, Mockery::on(function ($data) {
            return $data['status'] === 'active';
        }))
        ->andReturn($response);

    return $webhooksEndpoint;
}

/**
 * Mock a successful HTTP response.
 */
function mockCommandTestSuccessResponse(): Response
{
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(200);
    $response->shouldReceive('json')->andReturn(['webhook' => ['id' => 'wh_123']]);

    return $response;
}
