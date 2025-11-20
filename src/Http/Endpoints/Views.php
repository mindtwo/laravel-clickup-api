<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Views
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Create a scope for workspace-level views.
     *
     * @param int|string $teamId The workspace/team ID
     */
    public function forWorkspace(int|string $teamId): ViewsScope
    {
        return new ViewsScope($this->api, 'team', $teamId);
    }

    /**
     * Create a scope for space-level views.
     *
     * @param int|string $spaceId The space ID
     */
    public function forSpace(int|string $spaceId): ViewsScope
    {
        return new ViewsScope($this->api, 'space', $spaceId);
    }

    /**
     * Create a scope for folder-level views.
     *
     * @param int|string $folderId The folder ID
     */
    public function forFolder(int|string $folderId): ViewsScope
    {
        return new ViewsScope($this->api, 'folder', $folderId);
    }

    /**
     * Create a scope for list-level views.
     *
     * @param int|string $listId The list ID
     */
    public function forList(int|string $listId): ViewsScope
    {
        return new ViewsScope($this->api, 'list', $listId);
    }

    /**
     * Get a specific view by ID.
     *
     * @param int|string $viewId The view ID
     *
     * @throws ConnectionException
     */
    public function get(int|string $viewId): Response
    {
        return $this->api->client->get(sprintf('/view/%s', $viewId));
    }

    /**
     * Update an existing view.
     *
     * @param int|string $viewId The view ID
     * @param array<string, mixed> $data View data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $viewId, array $data): Response
    {
        return $this->api->client->put(sprintf('/view/%s', $viewId), $data);
    }

    /**
     * Delete a view.
     *
     * @param int|string $viewId The view ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $viewId): Response
    {
        return $this->api->client->delete(sprintf('/view/%s', $viewId));
    }

    /**
     * Get all tasks in a view.
     *
     * @param int|string $viewId The view ID
     * @param array<string, mixed> $params Optional query parameters (page, order_by, etc.)
     *
     * @throws ConnectionException
     */
    public function tasks(int|string $viewId, array $params = []): Response
    {
        return $this->api->client->get(sprintf('/view/%s/task', $viewId), $params);
    }
}
