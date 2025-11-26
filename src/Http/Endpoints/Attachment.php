<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

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
    public function create(int|string $taskId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/task/%s/attachment', $taskId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_POST,
                body: $data,
                options: ['multipart' => true],
            );
        }

        return $this->api->client->asMultipart()->post($endpoint, $data);
    }
}
