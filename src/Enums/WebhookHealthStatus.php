<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Enums;

enum WebhookHealthStatus: string
{
    case ACTIVE = 'active';
    case FAILING = 'failing';
    case SUSPENDED = 'suspended';

    /**
     * Check if the webhook is healthy (active).
     */
    public function isHealthy(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if the webhook is failing.
     */
    public function isFailing(): bool
    {
        return $this === self::FAILING;
    }

    /**
     * Check if the webhook is suspended.
     */
    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    /**
     * Check if the webhook needs recovery (failing or suspended).
     */
    public function needsRecovery(): bool
    {
        return $this === self::FAILING || $this === self::SUSPENDED;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE    => 'Active',
            self::FAILING   => 'Failing',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * Get a description of the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::ACTIVE    => 'Webhook is healthy and receiving events',
            self::FAILING   => 'Webhook returns unsuccessful HTTP codes or exceeds 7 seconds',
            self::SUSPENDED => 'Webhook has reached 100 failed events and no longer receives events',
        };
    }
}
