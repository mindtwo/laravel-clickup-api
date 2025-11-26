<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

/**
 * TaskList endpoint for managing ClickUp lists.
 * Named "TaskList" to avoid conflict with PHP's List keyword.
 */
class TaskList
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all lists in a folder.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function index(int|string $folderId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/folder/%s/list', $folderId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Get folderless lists directly in a space.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function indexInSpace(int|string $spaceId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/space/%s/list', $spaceId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Get a single list by ID.
     *
     * @param int|string $listId The list ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $listId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/list/%s', $listId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Create a new list in a folder or space.
     *
     * @param int|string $parentId The folder ID or space ID
     * @param array<string, int|string> $data List data including:
     *                                        - name (string, required): List name
     *                                        - content (string, optional): List description
     *                                        - due_date (int, optional): Due date in Unix milliseconds
     *                                        - due_date_time (bool, optional): Include time in due date
     *                                        - priority (int, optional): Priority level
     *                                        - assignee (int, optional): User ID of assignee
     *                                        - status (string, optional): List status
     * @param bool $inFolder Whether the parent is a folder (true) or space (false)
     *
     * @throws ConnectionException
     */
    public function create(int|string $parentId, array $data, bool $inFolder = true): Response|ClickUpApiCallJob
    {
        $endpoint = $inFolder
            ? sprintf('/folder/%s/list', $parentId)
            : sprintf('/space/%s/list', $parentId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_POST,
                body: $data,
            );
        }

        return $this->api->client->post($endpoint, $data);
    }

    /**
     * Update an existing list.
     *
     * @param int|string $listId The list ID
     * @param array<string, int|string> $data List data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $listId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/list/%s', $listId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_PUT,
                body: $data,
            );
        }

        return $this->api->client->put($endpoint, $data);
    }

    /**
     * Delete a list.
     *
     * @param int|string $listId The list ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $listId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/list/%s', $listId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_DELETE,
            );
        }

        return $this->api->client->delete($endpoint);
    }
}
