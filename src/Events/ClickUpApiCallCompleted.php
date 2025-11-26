<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClickUpApiCallCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $endpoint,
        public string $method,
        public array $response,
        public int $statusCode,
        public bool $successful,
    ) {}

    /**
     * Get the response data.
     */
    public function getData(): array
    {
        return $this->response;
    }

    /**
     * Check if the API call was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
