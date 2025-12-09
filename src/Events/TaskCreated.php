<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Mindtwo\LaravelClickUpApi\Enums\EventSource;

class TaskCreated extends ClickUpEvent
{

    /**
     * Get the task ID from the payload.
     */
    public function getTaskId(): string|int
    {
        return $this->source === EventSource::WEBHOOK
            ? $this->payload['task_id']
            : $this->payload['id'];
    }

    /**
     * Get the task data from the payload.
     */
    public function getTaskData(): array
    {
        return $this->payload;
    }

    /**
     * Get the custom fields of the created task.
     */
    public function getCustomFields(): array
    {
        return $this->payload['custom_fields'] ?? [];
    }

    /**
     * Get the list ID.
     */
    public function getListId(): string|int|null
    {
        return $this->source === EventSource::WEBHOOK
            ? $this->payload['list_id'] ?? null
            : $this->payload['list']['id'] ?? null;
    }
}
