<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class Space
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all spaces in a workspace/team.
     *
     * @param int|string|null $teamId The workspace/team ID
     * @param bool $archived Include archived spaces
     */
    public function index(int|string|null $teamId = null, bool $archived = false): LazyResponseProxy
    {
        if (empty($teamId)) {
            $teamId = config('clickup-api.default_workspace_id');
        }

        if (empty($teamId)) {
            throw new \InvalidArgumentException('Team ID must be provided either as a parameter or in the configuration.');
        }

        $endpoint = sprintf('/team/%s/space', $teamId);
        $queryParams = ['archived' => $archived];

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET',
            queryParams: $queryParams
        );
    }

    /**
     * Get a single space by ID.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $spaceId): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
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
    public function create(int|string $teamId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/team/%s/space', $teamId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Update an existing space.
     *
     * @param int|string $spaceId The space ID
     * @param array<string, int|string> $data Space data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $spaceId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'PUT',
            body: $data
        );
    }

    /**
     * Delete a space from the workspace.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $spaceId): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE'
        );
    }
}
