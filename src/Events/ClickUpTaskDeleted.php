<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClickUpTaskDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string|int $taskId,
        public string $endpoint,
        public bool $successful,
    ) {}

    /**
     * Get the task ID that was deleted.
     */
    public function getTaskId(): string|int
    {
        return $this->taskId;
    }

    /**
     * Check if the task deletion was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }
}
