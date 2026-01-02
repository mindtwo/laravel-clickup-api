<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Enums;

enum EventSource: string
{
    case API = 'api';
    case WEBHOOK = 'webhook';

    /**
     * Check if this is from an API call.
     */
    public function isApi(): bool
    {
        return $this === self::API;
    }

    /**
     * Check if this is from a webhook.
     */
    public function isWebhook(): bool
    {
        return $this === self::WEBHOOK;
    }
}
