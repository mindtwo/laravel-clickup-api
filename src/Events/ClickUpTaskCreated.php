<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClickUpTaskCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string|int $listId,
        public string|int $taskId,
        public array $taskData,
        public string $endpoint,
        public bool $successful,
    ) {}

    /**
     * Get the task data from the response.
     */
    public function getTaskData(): array
    {
        return $this->taskData;
    }

    /**
     * Get the task ID.
     */
    public function getTaskId(): string|int
    {
        return $this->taskId;
    }

    /**
     * Get the custom fields of the created task.
     */
    public function getCustomFields(): array
    {
        return $this->taskData['custom_fields'] ?? [];
    }

    /**
     * Get the list ID.
     */
    public function getListId(): string|int
    {
        return $this->listId;
    }

    /**
     * Check if the task creation was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }
}
