<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClickUpTaskUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string|int $taskId,
        public array $taskData,
        public string $endpoint,
        public bool $successful,
    ) {}

    /**
     * Get the updated task data from the response.
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
     * Check if the task update was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }
}
