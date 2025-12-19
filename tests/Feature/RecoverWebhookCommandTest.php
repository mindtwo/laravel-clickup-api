<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Tests\TestCase;
use Mockery;

class RecoverWebhookCommandTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_requires_webhook_id_or_all_flag(): void
    {
        $this->artisan('clickup:webhook-recover')
            ->expectsOutput('Please provide either a webhook_id or use the --all flag')
            ->assertExitCode(2); // INVALID
    }

    public function test_command_recovers_single_webhook_by_id(): void
    {
        $webhook = $this->createFailingWebhook('wh_123');

        $webhooksEndpoint = $this->mockSuccessfulRecovery($webhook->clickup_webhook_id);
        $this->app->instance(Webhooks::class, $webhooksEndpoint);

        Log::shouldReceive('info')->once();

        $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
            ->expectsOutput('Attempting to recover webhook: wh_123')
            ->expectsOutput('  Status: failing')
            ->expectsOutput('  ✓ Successfully recovered webhook wh_123')
            ->assertExitCode(0); // SUCCESS

        $webhook->refresh();
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook->health_status);
        $this->assertTrue($webhook->is_active);
        $this->assertEquals(0, $webhook->fail_count);
    }

    public function test_command_returns_error_for_unknown_webhook_id(): void
    {
        $this->artisan('clickup:webhook-recover', ['webhook_id' => 'unknown'])
            ->expectsOutput('Webhook not found: unknown')
            ->assertExitCode(1); // FAILURE
    }

    public function test_command_recovers_all_failing_webhooks(): void
    {
        $webhook1 = $this->createFailingWebhook('wh_123');
        $webhook2 = $this->createSuspendedWebhook('wh_456');
        $this->createActiveWebhook('wh_789'); // Should not be recovered

        $webhooksEndpoint = Mockery::mock(Webhooks::class);
        $webhooksEndpoint->shouldReceive('update')
            ->twice()
            ->andReturn($this->mockSuccessResponse());

        $this->app->instance(Webhooks::class, $webhooksEndpoint);

        Log::shouldReceive('info')->times(2);

        $this->artisan('clickup:webhook-recover', ['--all' => true])
            ->expectsOutput('Found 2 webhook(s) to recover.')
            ->expectsOutput('Recovery complete: 2 successful, 0 failed')
            ->assertExitCode(0);

        $webhook1->refresh();
        $webhook2->refresh();

        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook1->health_status);
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook2->health_status);
        $this->assertTrue($webhook1->is_active);
        $this->assertTrue($webhook2->is_active);
    }

    public function test_command_handles_no_webhooks_needing_recovery(): void
    {
        $this->createActiveWebhook('wh_123');

        $this->artisan('clickup:webhook-recover', ['--all' => true])
            ->expectsOutput('No webhooks need recovery.')
            ->assertExitCode(0);
    }

    public function test_command_handles_api_errors_gracefully(): void
    {
        $webhook = $this->createFailingWebhook('wh_123');

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
        $this->assertEquals(WebhookHealthStatus::FAILING, $webhook->health_status);
        $this->assertFalse($webhook->is_active);
    }

    public function test_command_handles_exceptions_gracefully(): void
    {
        $webhook = $this->createFailingWebhook('wh_123');

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
        $this->assertEquals(WebhookHealthStatus::FAILING, $webhook->health_status);
    }

    public function test_command_resets_fail_count_on_recovery(): void
    {
        $webhook = $this->createWebhook([
            'clickup_webhook_id' => 'wh_123',
            'health_status'      => WebhookHealthStatus::FAILING,
            'is_active'          => false,
            'fail_count'         => 75,
        ]);

        $webhooksEndpoint = $this->mockSuccessfulRecovery('wh_123');
        $this->app->instance(Webhooks::class, $webhooksEndpoint);

        Log::shouldReceive('info')->once();

        $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
            ->assertExitCode(0);

        $webhook->refresh();
        $this->assertEquals(0, $webhook->fail_count);
    }

    public function test_command_recovers_all_with_mixed_results(): void
    {
        $webhook1 = $this->createFailingWebhook('wh_success');
        $webhook2 = $this->createFailingWebhook('wh_failure');

        $webhooksEndpoint = Mockery::mock(Webhooks::class);

        // First webhook succeeds
        $webhooksEndpoint->shouldReceive('update')
            ->once()
            ->with('wh_success', Mockery::any())
            ->andReturn($this->mockSuccessResponse());

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

        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook1->health_status);
        $this->assertEquals(WebhookHealthStatus::FAILING, $webhook2->health_status);
    }

    public function test_command_displays_webhook_details(): void
    {
        $webhook = $this->createWebhook([
            'clickup_webhook_id' => 'wh_123',
            'endpoint'           => 'https://example.com/webhook',
            'health_status'      => WebhookHealthStatus::SUSPENDED,
            'is_active'          => false,
            'fail_count'         => 100,
        ]);

        $webhooksEndpoint = $this->mockSuccessfulRecovery('wh_123');
        $this->app->instance(Webhooks::class, $webhooksEndpoint);

        Log::shouldReceive('info')->once();

        $this->artisan('clickup:webhook-recover', ['webhook_id' => 'wh_123'])
            ->expectsOutput('Attempting to recover webhook: wh_123')
            ->expectsOutput('  Status: suspended')
            ->expectsOutput('  Endpoint: https://example.com/webhook')
            ->expectsOutput('  Fail count: 100')
            ->assertExitCode(0);
    }

    /**
     * Create a failing webhook.
     */
    protected function createFailingWebhook(string $webhookId): ClickUpWebhook
    {
        return $this->createWebhook([
            'clickup_webhook_id' => $webhookId,
            'health_status'      => WebhookHealthStatus::FAILING,
            'is_active'          => false,
            'fail_count'         => 50,
        ]);
    }

    /**
     * Create a suspended webhook.
     */
    protected function createSuspendedWebhook(string $webhookId): ClickUpWebhook
    {
        return $this->createWebhook([
            'clickup_webhook_id' => $webhookId,
            'health_status'      => WebhookHealthStatus::SUSPENDED,
            'is_active'          => false,
            'fail_count'         => 100,
        ]);
    }

    /**
     * Create an active webhook.
     */
    protected function createActiveWebhook(string $webhookId): ClickUpWebhook
    {
        return $this->createWebhook([
            'clickup_webhook_id' => $webhookId,
            'health_status'      => WebhookHealthStatus::ACTIVE,
            'is_active'          => true,
            'fail_count'         => 0,
        ]);
    }

    /**
     * Create a test webhook in the database.
     */
    protected function createWebhook(array $attributes): ClickUpWebhook
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
    protected function mockSuccessfulRecovery(string $webhookId): Webhooks
    {
        $response = $this->mockSuccessResponse();

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
    protected function mockSuccessResponse(): Response
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')->andReturn(200);
        $response->shouldReceive('json')->andReturn(['webhook' => ['id' => 'wh_123']]);

        return $response;
    }
}
