<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Space
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all spaces in a workspace/team.
     *
     * @param int|string $teamId The workspace/team ID
     * @param bool $archived Include archived spaces
     *
     * @throws ConnectionException
     */
    public function index(int|string $teamId, bool $archived = false): Response
    {
        return $this->api->client->get(sprintf('/team/%s/space', $teamId), [
            'archived' => $archived,
        ]);
    }

    /**
     * Get a single space by ID.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $spaceId): Response
    {
        return $this->api->client->get(sprintf('/space/%s', $spaceId));
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
    public function create(int|string $teamId, array $data): Response
    {
        return $this->api->client->post(sprintf('/team/%s/space', $teamId), $data);
    }

    /**
     * Update an existing space.
     *
     * @param int|string $spaceId The space ID
     * @param array<string, int|string> $data Space data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $spaceId, array $data): Response
    {
        return $this->api->client->put(sprintf('/space/%s', $spaceId), $data);
    }

    /**
     * Delete a space from the workspace.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $spaceId): Response
    {
        return $this->api->client->delete(sprintf('/space/%s', $spaceId));
    }
}
