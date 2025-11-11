<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

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
    public function create(int|string $taskId, array $data): Response
    {
        return $this->api->client->asMultipart()->post(sprintf('/task/%s/attachment', $taskId), $data);
    }
}
