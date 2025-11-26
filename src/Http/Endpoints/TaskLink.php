<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

class TaskLink
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Create a link between two tasks.
     *
     * Task links are simple bi-directional connections between tasks.
     * Unlike dependencies, links do not imply any workflow or blocking relationship.
     * They are useful for indicating that tasks are related or connected in some way.
     *
     * @param int|string $taskId The first task ID
     * @param int|string $linksToTaskId The second task ID to link with
     *
     * @throws ConnectionException
     */
    public function create(int|string $taskId, int|string $linksToTaskId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/task/%s/link/%s', $taskId, $linksToTaskId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_POST,
            );
        }

        return $this->api->client->post($endpoint);
    }

    /**
     * Remove a link between two tasks.
     *
     * This deletes the bi-directional link between the two tasks.
     *
     * @param int|string $taskId The first task ID
     * @param int|string $linksToTaskId The second task ID to unlink from
     *
     * @throws ConnectionException
     */
    public function delete(int|string $taskId, int|string $linksToTaskId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/task/%s/link/%s', $taskId, $linksToTaskId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_DELETE,
            );
        }

        return $this->api->client->delete($endpoint);
    }
}
