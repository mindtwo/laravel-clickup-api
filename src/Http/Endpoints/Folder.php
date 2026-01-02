<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

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
    public function index(int|string $spaceId): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s/folder', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * Get a single folder by ID.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $folderId): LazyResponseProxy
    {
        $endpoint = sprintf('/folder/%s', $folderId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
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
    public function create(int|string $spaceId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s/folder', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Update an existing folder.
     *
     * @param int|string $folderId The folder ID
     * @param array<string, int|string> $data Folder data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $folderId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/folder/%s', $folderId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'PUT',
            body: $data
        );
    }

    /**
     * Delete a folder from the workspace.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $folderId): LazyResponseProxy
    {
        $endpoint = sprintf('/folder/%s', $folderId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE'
        );
    }
}
