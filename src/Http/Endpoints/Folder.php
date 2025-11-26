<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

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
    public function index(int|string $spaceId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/space/%s/folder', $spaceId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Get a single folder by ID.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $folderId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/folder/%s', $folderId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
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
    public function create(int|string $spaceId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/space/%s/folder', $spaceId);

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
     * Update an existing folder.
     *
     * @param int|string $folderId The folder ID
     * @param array<string, int|string> $data Folder data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $folderId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/folder/%s', $folderId);

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
     * Delete a folder from the workspace.
     *
     * @param int|string $folderId The folder ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $folderId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/folder/%s', $folderId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_DELETE,
            );
        }

        return $this->api->client->delete($endpoint);
    }
}
