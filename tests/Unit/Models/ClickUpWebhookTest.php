<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhookDelivery;
use Mindtwo\LaravelClickUpApi\Tests\TestCase;

class ClickUpWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        foreach (File::allFiles(__DIR__.'/../../../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    public function test_webhook_has_deliveries_relationship(): void
    {
        $webhook = $this->createWebhook();

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

        $this->assertCount(2, $webhook->deliveries);
        $this->assertInstanceOf(ClickUpWebhookDelivery::class, $webhook->deliveries->first());
    }

    public function test_webhook_casts_health_status_to_enum(): void
    {
        $webhook = $this->createWebhook(['health_status' => 'active']);

        $this->assertInstanceOf(WebhookHealthStatus::class, $webhook->health_status);
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook->health_status);
    }

    public function test_webhook_casts_dates_correctly(): void
    {
        $webhook = $this->createWebhook([
            'last_triggered_at' => '2025-01-01 12:00:00',
            'health_checked_at' => '2025-01-02 12:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $webhook->last_triggered_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $webhook->health_checked_at);
    }

    public function test_webhook_casts_last_error_to_array(): void
    {
        $error = ['error' => 'Test error', 'timestamp' => '2025-01-01T12:00:00'];
        $webhook = $this->createWebhook(['last_error' => $error]);

        $this->assertIsArray($webhook->last_error);
        $this->assertEquals($error, $webhook->last_error);
    }

    public function test_record_delivery_increments_counter_and_updates_timestamp(): void
    {
        $webhook = $this->createWebhook(['total_deliveries' => 5]);

        $webhook->recordDelivery();

        $webhook->refresh();
        $this->assertEquals(6, $webhook->total_deliveries);
        $this->assertNotNull($webhook->last_triggered_at);
    }

    public function test_record_failure_increments_counter_and_stores_error(): void
    {
        $webhook = $this->createWebhook(['failed_deliveries' => 2]);

        $webhook->recordFailure('Test error message');

        $webhook->refresh();
        $this->assertEquals(3, $webhook->failed_deliveries);
        $this->assertIsArray($webhook->last_error);
        $this->assertEquals('Test error message', $webhook->last_error['error']);
        $this->assertArrayHasKey('timestamp', $webhook->last_error);
    }

    public function test_failure_rate_attribute_calculates_correctly(): void
    {
        $webhook = $this->createWebhook([
            'total_deliveries'  => 100,
            'failed_deliveries' => 25,
        ]);

        $this->assertEquals(25.0, $webhook->failure_rate);
    }

    public function test_failure_rate_returns_zero_for_no_deliveries(): void
    {
        $webhook = $this->createWebhook([
            'total_deliveries'  => 0,
            'failed_deliveries' => 0,
        ]);

        $this->assertEquals(0.0, $webhook->failure_rate);
    }

    public function test_is_healthy_returns_true_for_active_status(): void
    {
        $webhook = $this->createWebhook(['health_status' => WebhookHealthStatus::ACTIVE]);

        $this->assertTrue($webhook->isHealthy());
    }

    public function test_is_healthy_returns_false_for_failing_status(): void
    {
        $webhook = $this->createWebhook(['health_status' => WebhookHealthStatus::FAILING]);

        $this->assertFalse($webhook->isHealthy());
    }

    public function test_is_healthy_returns_false_for_suspended_status(): void
    {
        $webhook = $this->createWebhook(['health_status' => WebhookHealthStatus::SUSPENDED]);

        $this->assertFalse($webhook->isHealthy());
    }

    public function test_healthy_scope_filters_active_webhooks(): void
    {
        $this->createWebhook(['clickup_webhook_id' => 'wh_1', 'health_status' => WebhookHealthStatus::ACTIVE]);
        $this->createWebhook(['clickup_webhook_id' => 'wh_2', 'health_status' => WebhookHealthStatus::FAILING]);
        $this->createWebhook(['clickup_webhook_id' => 'wh_3', 'health_status' => WebhookHealthStatus::SUSPENDED]);

        $healthyWebhooks = ClickUpWebhook::healthy()->get();

        $this->assertCount(1, $healthyWebhooks);
        $this->assertEquals('wh_1', $healthyWebhooks->first()->clickup_webhook_id);
    }

    public function test_failing_scope_filters_failing_webhooks(): void
    {
        $this->createWebhook(['clickup_webhook_id' => 'wh_1', 'health_status' => WebhookHealthStatus::ACTIVE]);
        $this->createWebhook(['clickup_webhook_id' => 'wh_2', 'health_status' => WebhookHealthStatus::FAILING]);
        $this->createWebhook(['clickup_webhook_id' => 'wh_3', 'health_status' => WebhookHealthStatus::FAILING]);

        $failingWebhooks = ClickUpWebhook::failing()->get();

        $this->assertCount(2, $failingWebhooks);
    }

    public function test_suspended_scope_filters_suspended_webhooks(): void
    {
        $this->createWebhook(['clickup_webhook_id' => 'wh_1', 'health_status' => WebhookHealthStatus::ACTIVE]);
        $this->createWebhook(['clickup_webhook_id' => 'wh_2', 'health_status' => WebhookHealthStatus::SUSPENDED]);
        $this->createWebhook(['clickup_webhook_id' => 'wh_3', 'health_status' => WebhookHealthStatus::FAILING]);

        $suspendedWebhooks = ClickUpWebhook::suspended()->get();

        $this->assertCount(1, $suspendedWebhooks);
        $this->assertEquals('wh_2', $suspendedWebhooks->first()->clickup_webhook_id);
    }

    public function test_needs_recovery_scope_filters_correctly(): void
    {
        $this->createWebhook([
            'clickup_webhook_id' => 'wh_1',
            'health_status'      => WebhookHealthStatus::ACTIVE,
            'is_active'          => true,
        ]);
        $this->createWebhook([
            'clickup_webhook_id' => 'wh_2',
            'health_status'      => WebhookHealthStatus::FAILING,
            'is_active'          => false,
        ]);
        $this->createWebhook([
            'clickup_webhook_id' => 'wh_3',
            'health_status'      => WebhookHealthStatus::SUSPENDED,
            'is_active'          => false,
        ]);
        $this->createWebhook([
            'clickup_webhook_id' => 'wh_4',
            'health_status'      => WebhookHealthStatus::FAILING,
            'is_active'          => true, // Still active, doesn't need recovery
        ]);

        $needsRecovery = ClickUpWebhook::needsRecovery()->get();

        $this->assertCount(2, $needsRecovery);
        $this->assertTrue($needsRecovery->contains('clickup_webhook_id', 'wh_2'));
        $this->assertTrue($needsRecovery->contains('clickup_webhook_id', 'wh_3'));
    }

    public function test_update_health_status_sets_active_with_no_recent_deliveries(): void
    {
        $webhook = $this->createWebhook(['health_status' => WebhookHealthStatus::FAILING]);

        $webhook->updateHealthStatus();

        $webhook->refresh();
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook->health_status);
    }

    public function test_update_health_status_sets_failing_with_high_failure_rate(): void
    {
        $webhook = $this->createWebhook(['health_status' => WebhookHealthStatus::ACTIVE]);

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
        $this->assertEquals(WebhookHealthStatus::FAILING, $webhook->health_status);
    }

    public function test_update_health_status_ignores_old_deliveries(): void
    {
        $webhook = $this->createWebhook(['health_status' => WebhookHealthStatus::ACTIVE]);

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
        $this->assertEquals(WebhookHealthStatus::ACTIVE, $webhook->health_status);
    }

    public function test_soft_deletes_work_correctly(): void
    {
        $webhook = $this->createWebhook(['clickup_webhook_id' => 'wh_test']);

        $webhook->delete();

        $this->assertSoftDeleted('clickup_webhooks', [
            'clickup_webhook_id' => 'wh_test',
        ]);

        $this->assertCount(0, ClickUpWebhook::all());
        $this->assertCount(1, ClickUpWebhook::withTrashed()->get());
    }

    public function test_webhook_has_correct_table_name(): void
    {
        $webhook = new ClickUpWebhook;

        $this->assertEquals('clickup_webhooks', $webhook->getTable());
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
            'health_status'      => WebhookHealthStatus::ACTIVE,
            'total_deliveries'   => 0,
            'failed_deliveries'  => 0,
            'fail_count'         => 0,
        ], $overrides));
    }
}
