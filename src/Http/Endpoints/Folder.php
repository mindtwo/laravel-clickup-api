<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Folder
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all folders in a space.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function index(int|string $spaceId): Response
    {
        return $this->api->client->get(sprintf('/space/%s/folder', $spaceId));
    }

    /**
     * Get a single folder by ID.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $folderId): Response
    {
        return $this->api->client->get(sprintf('/folder/%s', $folderId));
    }

    /**
     * Create a new folder in a space.
     *
     * @param int|string $spaceId The space ID
     * @param array<string, int|string> $data Folder data including:
     *                                        - name (string, required): Folder name
     *                                        - hidden (bool, optional): Whether the folder is hidden
     *
     * @throws ConnectionException
     */
    public function create(int|string $spaceId, array $data): Response
    {
        return $this->api->client->post(sprintf('/space/%s/folder', $spaceId), $data);
    }

    /**
     * Update an existing folder.
     *
     * @param int|string $folderId The folder ID
     * @param array<string, int|string> $data Folder data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $folderId, array $data): Response
    {
        return $this->api->client->put(sprintf('/folder/%s', $folderId), $data);
    }

    /**
     * Delete a folder from the workspace.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $folderId): Response
    {
        return $this->api->client->delete(sprintf('/folder/%s', $folderId));
    }
}
