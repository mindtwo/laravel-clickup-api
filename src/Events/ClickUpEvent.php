<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;

abstract class ClickUpEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param array $payload The event data payload
     * @param EventSource $source The source of the event (API or Webhook)
     * @param bool $successful Whether the operation was successful
     */
    public function __construct(
        public array $payload,
        public EventSource $source = EventSource::API,
        public bool $successful = true,
    ) {}

    /**
     * Check if this event came from a webhook.
     */
    public function isFromWebhook(): bool
    {
        return $this->source->isWebhook();
    }

    /**
     * Check if this event came from an API call.
     */
    public function isFromApi(): bool
    {
        return $this->source->isApi();
    }

    /**
     * Check if the operation was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Get the history items (what changed) from webhook payload.
     * Only available for webhook events.
     */
    public function getHistoryItems(): array
    {
        return $this->payload['history_items'] ?? [];
    }
}
