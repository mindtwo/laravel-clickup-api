<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Tests\Unit\Jobs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Jobs\CheckWebhookHealth;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Tests\TestCase;
use Mockery;

class CheckWebhookHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        foreach (File::allFiles(__DIR__.'/../../../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }

        // Set workspace ID for testing
        config(['clickup-api.default_workspace_id' => 'workspace_123']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_syncs_webhook_health_status_from_api(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        $webhooksEndpoint = $this->mockWebhooksEndpoint([
            [
                'id'         => 'wh_123',
                'status'     => 'active',
                'fail_count' => 0,
            ],
        ]);

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        $webhook->refresh();
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook->health_status);
        $this->assertEquals(0, $webhook->fail_count);
        $this->assertNotNull($webhook->health_checked_at);
    }

    public function test_job_updates_webhook_to_failing_status(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        $webhooksEndpoint = $this->mockWebhooksEndpoint([
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
        $this->assertEquals(WebhookHealthStatus::FAILING, $webhook->health_status);
        $this->assertEquals(45, $webhook->fail_count);
        $this->assertFalse($webhook->is_active); // Should be auto-disabled
    }

    public function test_job_updates_webhook_to_suspended_status(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        $webhooksEndpoint = $this->mockWebhooksEndpoint([
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
        $this->assertEquals(WebhookHealthStatus::SUSPENDED, $webhook->health_status);
        $this->assertEquals(100, $webhook->fail_count);
        $this->assertFalse($webhook->is_active);
    }

    public function test_job_tracks_fail_count_changes(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        // First check - some failures
        $webhooksEndpoint = $this->mockWebhooksEndpoint([
            [
                'id'         => 'wh_123',
                'status'     => 'active',
                'fail_count' => 10,
            ],
        ]);

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        $webhook->refresh();
        $this->assertEquals(10, $webhook->fail_count);
        $this->assertTrue($webhook->is_active); // Still active

        // Second check - more failures but still active
        $webhooksEndpoint2 = $this->mockWebhooksEndpoint([
            [
                'id'         => 'wh_123',
                'status'     => 'active',
                'fail_count' => 25,
            ],
        ]);

        $job2 = new CheckWebhookHealth;
        $job2->handle($webhooksEndpoint2);

        $webhook->refresh();
        $this->assertEquals(25, $webhook->fail_count);
        $this->assertTrue($webhook->is_active);
    }

    public function test_job_handles_multiple_webhooks(): void
    {
        $webhook1 = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);
        $webhook2 = $this->createWebhook('wh_456', WebhookHealthStatus::ACTIVE);

        $webhooksEndpoint = $this->mockWebhooksEndpoint([
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

        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook1->health_status);
        $this->assertTrue($webhook1->is_active);

        $this->assertEquals(WebhookHealthStatus::FAILING, $webhook2->health_status);
        $this->assertFalse($webhook2->is_active); // Auto-disabled
    }

    public function test_job_skips_webhooks_not_in_database(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        // API returns webhook that doesn't exist in our database
        $webhooksEndpoint = $this->mockWebhooksEndpoint([
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

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        // Should only update wh_123
        $this->assertCount(1, ClickUpWebhook::all());
        $webhook->refresh();
        $this->assertNotNull($webhook->health_checked_at);
    }

    public function test_job_handles_missing_workspace_id_gracefully(): void
    {
        config(['clickup-api.default_workspace_id' => null]);

        Log::shouldReceive('info')
            ->once()
            ->with('No workspace found for health check');

        $webhooksEndpoint = Mockery::mock(Webhooks::class);
        // Should not call index() method

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_job_handles_api_errors_gracefully(): void
    {
        $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')->andReturn(500);

        $webhooksEndpoint = Mockery::mock(Webhooks::class);
        $webhooksEndpoint->shouldReceive('index')
            ->with('workspace_123')
            ->once()
            ->andReturn($response);

        Log::shouldReceive('info')->once(); // Starting check
        Log::shouldReceive('warning')->once(); // Failed API call

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        // Webhook should not be updated
        $webhook = ClickUpWebhook::first();
        $this->assertNull($webhook->health_checked_at);
    }

    public function test_job_logs_status_changes(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        $webhooksEndpoint = $this->mockWebhooksEndpoint([
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
    }

    public function test_job_handles_webhooks_with_default_status(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        // API response without explicit status (should default to 'active')
        $webhooksEndpoint = $this->mockWebhooksEndpoint([
            [
                'id' => 'wh_123',
                // No 'status' field
                'fail_count' => 0,
            ],
        ]);

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        $webhook->refresh();
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook->health_status);
    }

    public function test_job_handles_webhooks_with_missing_fail_count(): void
    {
        $webhook = $this->createWebhook('wh_123', WebhookHealthStatus::ACTIVE);

        // API response without fail_count (should default to 0)
        $webhooksEndpoint = $this->mockWebhooksEndpoint([
            [
                'id'     => 'wh_123',
                'status' => 'active',
                // No 'fail_count' field
            ],
        ]);

        $job = new CheckWebhookHealth;
        $job->handle($webhooksEndpoint);

        $webhook->refresh();
        $this->assertEquals(0, $webhook->fail_count);
    }

    /**
     * Create a test webhook in the database.
     */
    protected function createWebhook(string $webhookId, WebhookHealthStatus $status): ClickUpWebhook
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
    protected function mockWebhooksEndpoint(array $webhooks): Webhooks
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')->andReturn(200);
        $response->shouldReceive('json')->andReturn(['webhooks' => $webhooks]);

        $webhooksEndpoint = Mockery::mock(Webhooks::class);
        $webhooksEndpoint->shouldReceive('index')
            ->with('workspace_123')
            ->once()
            ->andReturn($response);

        return $webhooksEndpoint;
    }
}
