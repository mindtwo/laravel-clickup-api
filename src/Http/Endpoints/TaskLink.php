<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

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
    public function create(int|string $taskId, int|string $linksToTaskId): Response
    {
        return $this->api->client->post(
            sprintf('/task/%s/link/%s', $taskId, $linksToTaskId)
        );
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
    public function delete(int|string $taskId, int|string $linksToTaskId): Response
    {
        return $this->api->client->delete(
            sprintf('/task/%s/link/%s', $taskId, $linksToTaskId)
        );
    }
}
