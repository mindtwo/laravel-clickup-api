<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

class Space
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all spaces in a workspace/team.
     *
     * @param ?int|string $teamId The workspace/team ID
     * @param bool $archived Include archived spaces
     *
     * @throws ConnectionException
     */
    public function index(int|string|null $teamId = null, bool $archived = false): Response|ClickUpApiCallJob
    {
        if (empty($teamId)) {
            $teamId = config('clickup-api.default_workspace_id');
        }

        if (empty($teamId)) {
            throw new \InvalidArgumentException('Team ID must be provided either as a parameter or in the configuration.');
        }

        $endpoint = sprintf('/team/%s/space', $teamId);
        $queryParams = ['archived' => $archived];

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
                queryParams: $queryParams,
            );
        }

        return $this->api->client->get($endpoint, $queryParams);
    }

    /**
     * Get a single space by ID.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $spaceId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/space/%s', $spaceId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Create a new space in a workspace/team.
     *
     * @param int|string $teamId The workspace/team ID
     * @param array<string, int|string> $data Space data including:
     *                                        - name (string, required): Space name
     *                                        - multiple_assignees (bool, optional): Enable multiple assignees
     *                                        - features (array, optional): Feature toggles including:
     *                                        - due_dates (array): enabled, start_date, remap_due_dates,
     *                                        remap_closed_due_date
     *                                        - time_tracking (array): enabled
     *                                        - tags (array): enabled
     *                                        - time_estimates (array): enabled
     *                                        - checklists (array): enabled
     *                                        - custom_fields (array): enabled
     *                                        - remap_dependencies (array): enabled
     *                                        - dependency_warning (array): enabled
     *                                        - portfolios (array): enabled
     *
     * @throws ConnectionException
     */
    public function create(int|string $teamId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/team/%s/space', $teamId);

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
     * Update an existing space.
     *
     * @param int|string $spaceId The space ID
     * @param array<string, int|string> $data Space data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $spaceId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/space/%s', $spaceId);

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
     * Delete a space from the workspace.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $spaceId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/space/%s', $spaceId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_DELETE,
            );
        }

        return $this->api->client->delete($endpoint);
    }
}
