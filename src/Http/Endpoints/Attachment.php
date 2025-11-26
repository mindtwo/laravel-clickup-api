<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class Attachment
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Upload an attachment to a task.
     *
     * @param int|string $taskId The task ID
     * @param array<string, int|string> $data Attachment data including:
     *                                        - attachment (resource): File resource to upload
     *                                        - filename (string): Name of the file
     *
     * @throws ConnectionException
     */
    public function create(int|string $taskId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s/attachment', $taskId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data,
            options: ['multipart' => true]
        );
    }
}
