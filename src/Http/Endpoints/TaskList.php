<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

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
    public function index(int|string $folderId): LazyResponseProxy
    {
        $endpoint = sprintf('/folder/%s/list', $folderId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * Get folderless lists directly in a space.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function indexInSpace(int|string $spaceId): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s/list', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * Get a single list by ID.
     *
     * @param int|string $listId The list ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $listId): LazyResponseProxy
    {
        $endpoint = sprintf('/list/%s', $listId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
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
    public function create(int|string $parentId, array $data, bool $inFolder = true): LazyResponseProxy
    {
        $endpoint = $inFolder
            ? sprintf('/folder/%s/list', $parentId)
            : sprintf('/space/%s/list', $parentId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Update an existing list.
     *
     * @param int|string $listId The list ID
     * @param array<string, int|string> $data List data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $listId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/list/%s', $listId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'PUT',
            body: $data
        );
    }

    /**
     * Delete a list.
     *
     * @param int|string $listId The list ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $listId): LazyResponseProxy
    {
        $endpoint = sprintf('/list/%s', $listId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE'
        );
    }
}
