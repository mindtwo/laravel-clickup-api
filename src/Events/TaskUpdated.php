<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Mindtwo\LaravelClickUpApi\Enums\EventSource;

class TaskUpdated extends ClickUpEvent
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
}
