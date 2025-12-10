<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClickUpWebhookDelivery extends Model
{
    protected $table = 'clickup_webhook_deliveries';

    protected $fillable = [
        'clickup_webhook_id',
        'event',
        'payload',
        'status',
        'error_message',
        'processing_time_ms',
        'idempotency_key',
    ];

    protected $casts = [
        'payload'            => 'array',
        'processing_time_ms' => 'integer',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(ClickUpWebhook::class, 'clickup_webhook_id');
    }

    /**
     * Check if the delivery was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if the delivery failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the processing time in seconds.
     */
    public function getProcessingTimeInSecondsAttribute(): ?float
    {
        if ($this->processing_time_ms === null) {
            return null;
        }

        return $this->processing_time_ms / 1000;
    }
}
