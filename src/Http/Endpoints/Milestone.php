<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class Milestone
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all custom task types (including milestones) for a workspace/team.
     *
     * Custom task types include milestones and other task type variations.
     * This endpoint is required to retrieve the milestone type ID needed for creating milestones.
     *
     * @param int|string $teamId The workspace/team ID
     *
     * @throws ConnectionException
     */
    public function getCustomTaskTypes(int|string $teamId): LazyResponseProxy
    {
        $endpoint = sprintf('/team/%s/custom_item', $teamId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * Helper method to find a milestone task type ID by name.
     *
     * This searches through the custom task types and returns the ID of the first
     * task type matching the given name (case-insensitive).
     *
     * @param int|string $teamId The workspace/team ID
     * @param string $milestoneName The name of the milestone type (default: "Milestone")
     *
     * @throws ConnectionException
     *
     * @return string|null The milestone type ID, or null if not found
     */
    public function getMilestoneTypeId(int|string $teamId, string $milestoneName = 'Milestone'): ?string
    {
        $response = $this->getCustomTaskTypes($teamId);

        /**
         * @var array<string, array<string, int|string>|int|string> $data Contains 'task', 'dependencies',
         *                                                          'fields', 'custom_items', and 'linked_tasks' keys
         */
        $data = $response->json();

        /**
         * Search through custom items to find the milestone type.
         *
         * @var array<string, int|string> $customItems
         */
        $customItems = $data['custom_items'] ?? [];

        foreach ($customItems as $item) {
            if (strcasecmp($item['name'] ?? '', $milestoneName) === 0) {
                return $item['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Create a milestone task.
     *
     * This is a convenience method that creates a task with the milestone custom type.
     * You must provide the milestone type ID, which can be obtained using getMilestoneTypeId().
     *
     * @param int|string $listId The list ID where the milestone will be created
     * @param string $name The milestone name
     * @param int|string $customTypeId The milestone custom task type ID
     * @param array<string, int|string> $additionalData Optional additional task data (description, assignees, due_date, etc.)
     *
     * @throws ConnectionException
     */
    public function create(int|string $listId, string $name, int|string $customTypeId, array $additionalData = []): LazyResponseProxy
    {
        $data = array_merge(
            [
                'name'        => $name,
                'custom_type' => (string) $customTypeId,
            ],
            $additionalData
        );

        $endpoint = sprintf('/list/%s/task', $listId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }
}
