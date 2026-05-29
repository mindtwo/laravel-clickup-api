<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class ClickUpApiCallFailedException extends RuntimeException
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $method,
        public readonly int $statusCode,
        public readonly array $response = [],
    ) {
        parent::__construct(sprintf(
            'ClickUp API call %s %s failed with status %d: %s',
            $method,
            $endpoint,
            $statusCode,
            $response['err'] ?? 'Unknown error',
        ));
    }

    /**
     * Build the exception from a received HTTP response.
     */
    public static function fromResponse(string $endpoint, string $method, Response $response): self
    {
        return new self($endpoint, $method, $response->status(), $response->json() ?? []);
    }
}
