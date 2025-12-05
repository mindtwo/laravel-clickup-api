<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClickUpWebhook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clickup_webhook_id',
        'endpoint',
        'event',
        'status',
        'health_status',
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
        'last_error' => 'array',
        'is_active' => 'boolean',
        'total_deliveries' => 'integer',
        'failed_deliveries' => 'integer',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(ClickUpWebhookDelivery::class);
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
                'error' => $error,
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
     * Check if this webhook is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    /**
     * Update the health status based on recent deliveries.
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
            $this->update(['health_status' => 'healthy']);

            return;
        }

        $recentFailureRate = ($recentFailures / $recentTotal) * 100;

        $healthStatus = match (true) {
            $recentFailureRate >= 50 => 'failing',
            $recentFailureRate >= 20 => 'degraded',
            default => 'healthy',
        };

        $this->update(['health_status' => $healthStatus]);
    }
}
