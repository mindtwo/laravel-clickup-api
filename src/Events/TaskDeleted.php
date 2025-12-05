<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;

class TaskDeleted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array  $payload  The task data payload
     * @param  EventSource  $source  The source of the event
     * @param  bool  $successful  Whether the operation was successful
     */
    public function __construct(
        public array $payload,
        public EventSource $source = EventSource::API,
        public bool $successful = true,
    ) {}

    /**
     * Get the task ID from the payload.
     */
    public function getTaskId(): string|int
    {
        return $this->source === EventSource::WEBHOOK
            ? $this->payload['task_id']
            : $this->payload['id'] ?? $this->payload['task_id'];
    }

    /**
     * Get the task data from the payload.
     */
    public function getTaskData(): array
    {
        return $this->payload;
    }

    /**
     * Check if the task deletion was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }

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
}
