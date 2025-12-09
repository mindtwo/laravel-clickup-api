<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

class TaskPriorityUpdated extends ClickUpEvent
{
    public function getTaskId(): string|int
    {
        return $this->payload['task_id'];
    }
}
