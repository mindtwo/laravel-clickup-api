<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;

/**
 * @property int $id
 * @property string|null $clickup_webhook_id
 * @property string $endpoint
 * @property string $event
 * @property WebhookHealthStatus $health_status
 * @property int $fail_count
 * @property Carbon|null $health_checked_at
 * @property string $target_type
 * @property string $target_id
 * @property string|null $secret
 * @property Carbon|null $last_triggered_at
 * @property int $total_deliveries
 * @property int $failed_deliveries
 * @property int $deliveries_since_recovery
 * @property int $failed_deliveries_since_recovery
 * @property int $recovery_count
 * @property Carbon|null $recovered_at
 * @property array<string, mixed>|null $last_error
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read float $failure_rate
 * @property-read float $failure_rate_since_recovery
 */
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
        'deliveries_since_recovery',
        'failed_deliveries_since_recovery',
        'recovery_count',
        'recovered_at',
        'last_error',
        'is_active',
    ];

    protected $casts = [
        'last_triggered_at'                => 'datetime',
        'health_checked_at'                => 'datetime',
        'recovered_at'                     => 'datetime',
        'health_status'                    => WebhookHealthStatus::class,
        'last_error'                       => 'array',
        'is_active'                        => 'boolean',
        'total_deliveries'                 => 'integer',
        'failed_deliveries'                => 'integer',
        'deliveries_since_recovery'        => 'integer',
        'failed_deliveries_since_recovery' => 'integer',
        'recovery_count'                   => 'integer',
        'fail_count'                       => 'integer',
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
        $this->increment('deliveries_since_recovery');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Record a failed webhook delivery.
     */
    public function recordFailure(string $error): void
    {
        $this->increment('failed_deliveries');
        $this->increment('failed_deliveries_since_recovery');
        $this->update([
            'last_error' => [
                'error'     => $error,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark this webhook as recovered, restoring it to a healthy state and
     * resetting the recovery-relative counters while preserving lifetime totals.
     *
     * Called both by the manual recovery command and by the health check job
     * when ClickUp reports a previously failing/suspended webhook as active again.
     */
    public function markRecovered(): void
    {
        $this->update([
            'health_status'                    => WebhookHealthStatus::ACTIVE,
            'is_active'                        => true,
            'fail_count'                       => 0,
            'deliveries_since_recovery'        => 0,
            'failed_deliveries_since_recovery' => 0,
            'last_error'                       => null,
            'recovered_at'                     => now(),
            'recovery_count'                   => $this->recovery_count + 1,
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
     * Get the failure rate of this webhook since its last recovery.
     */
    public function getFailureRateSinceRecoveryAttribute(): float
    {
        if ($this->deliveries_since_recovery === 0) {
            return 0.0;
        }

        return ($this->failed_deliveries_since_recovery / $this->deliveries_since_recovery) * 100;
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
     *
     * @param Builder<ClickUpWebhook> $query
     *
     * @return Builder<ClickUpWebhook>
     */
    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('health_status', WebhookHealthStatus::ACTIVE);
    }

    /**
     * Scope a query to only include failing webhooks.
     *
     * @param Builder<ClickUpWebhook> $query
     *
     * @return Builder<ClickUpWebhook>
     */
    public function scopeFailing(Builder $query): Builder
    {
        return $query->where('health_status', WebhookHealthStatus::FAILING);
    }

    /**
     * Scope a query to only include suspended webhooks.
     *
     * @param Builder<ClickUpWebhook> $query
     *
     * @return Builder<ClickUpWebhook>
     */
    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('health_status', WebhookHealthStatus::SUSPENDED);
    }

    /**
     * Scope a query to webhooks that need recovery.
     *
     * @param Builder<ClickUpWebhook> $query
     *
     * @return Builder<ClickUpWebhook>
     */
    public function scopeNeedsRecovery(Builder $query): Builder
    {
        return $query->whereIn('health_status', [
            WebhookHealthStatus::FAILING,
            WebhookHealthStatus::SUSPENDED,
        ])->where('is_active', false);
    }
}
