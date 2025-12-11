<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;

class ClickUpWebhook extends Model
{
    use SoftDeletes;

    protected $table = 'clickup_webhooks';

    protected $fillable = [
        'clickup_webhook_id',
        'endpoint',
        'event',
        'status',
        'health_status',
        'fail_count',
        'health_checked_at',
        'target_type',
        'target_id',
        'secret',
        'last_triggered_at',
        'total_deliveries',
        'failed_deliveries',
        'last_error',
        'is_active',
    ];

    protected $casts = [
        'last_triggered_at' => 'datetime',
        'health_checked_at' => 'datetime',
        'health_status'     => WebhookHealthStatus::class,
        'last_error'        => 'array',
        'is_active'         => 'boolean',
        'total_deliveries'  => 'integer',
        'failed_deliveries' => 'integer',
        'fail_count'        => 'integer',
    ];

    /**
     * @return HasMany<ClickUpWebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ClickUpWebhookDelivery::class, 'clickup_webhook_id', 'id');
    }

    /**
     * Record a successful webhook delivery.
     */
    public function recordDelivery(): void
    {
        $this->increment('total_deliveries');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Record a failed webhook delivery.
     */
    public function recordFailure(string $error): void
    {
        $this->increment('failed_deliveries');
        $this->update([
            'last_error' => [
                'error'     => $error,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the failure rate of this webhook.
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_deliveries === 0) {
            return 0.0;
        }

        return ($this->failed_deliveries / $this->total_deliveries) * 100;
    }

    /**
     * Check if this webhook is healthy (active status from ClickUp).
     */
    public function isHealthy(): bool
    {
        return $this->health_status === WebhookHealthStatus::ACTIVE;
    }

    /**
     * Update the health status based on recent deliveries.
     * Note: This uses local calculation. The CheckWebhookHealth job
     * will sync the authoritative status from ClickUp API.
     */
    public function updateHealthStatus(): void
    {
        $recentFailures = $this->deliveries()
            ->where('created_at', '>=', now()->subHours(24))
            ->where('status', 'failed')
            ->count();

        $recentTotal = $this->deliveries()
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentTotal === 0) {
            $this->update(['health_status' => WebhookHealthStatus::ACTIVE]);

            return;
        }

        $recentFailureRate = ($recentFailures / $recentTotal) * 100;

        // Use ClickUp's status values: active, failing, suspended
        $healthStatus = match (true) {
            $recentFailureRate >= 50 => WebhookHealthStatus::FAILING,
            default                  => WebhookHealthStatus::ACTIVE,
        };

        $this->update(['health_status' => $healthStatus]);
    }

    /**
     * Scope a query to only include healthy (active) webhooks.
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_status', WebhookHealthStatus::ACTIVE);
    }

    /**
     * Scope a query to only include failing webhooks.
     */
    public function scopeFailing($query)
    {
        return $query->where('health_status', WebhookHealthStatus::FAILING);
    }

    /**
     * Scope a query to only include suspended webhooks.
     */
    public function scopeSuspended($query)
    {
        return $query->where('health_status', WebhookHealthStatus::SUSPENDED);
    }

    /**
     * Scope a query to webhooks that need recovery.
     */
    public function scopeNeedsRecovery($query)
    {
        return $query->whereIn('health_status', [
            WebhookHealthStatus::FAILING,
            WebhookHealthStatus::SUSPENDED,
        ])->where('is_active', false);
    }
}
