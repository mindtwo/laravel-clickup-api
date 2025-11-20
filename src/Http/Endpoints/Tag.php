<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Tag
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the task Tags available in a Space.
     *
     * @param int|string $spaceId The space ID
     *
     * @throws ConnectionException
     */
    public function index(int|string $spaceId): Response
    {
        return $this->api->client->get(sprintf('/space/%s/tag', $spaceId));
    }

    /**
     * Create a new task Tag to a Space.
     *
     * @param int|string $spaceId The space ID
     * @param array<string, int|string> $tagObject data for the tag including:
     *                                             - name (string): Name of the tag
     *                                             - tag_fg (string): foreground color in hex format (e.g., #FFFFFF)
     *                                             - tag_bg (string): background color in hex format (e.g., #000000)
     *
     * @throws ConnectionException
     */
    public function create(int|string $listId, array $tagObject): Response
    {
        if (empty($tagObject['name'])) {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        if (empty($tagObject['tag_fg'])) {
            throw new \InvalidArgumentException('Tag foreground color (tag_fg) is required.');
        }

        if (empty($tagObject['tag_bg'])) {
            throw new \InvalidArgumentException('Tag background color (tag_bg) is required.');
        }

        return $this->api->client->post(sprintf('/space/%s/tag', $listId), ['tag' => $tagObject]);
    }

    /**
     * Update a task Tag.
     *
     * @param int|string $spaceId The space ID
     * @param string $tagName The name of the tag to update
     * @param array<string, int|string> $tagObject data for the tag including:
     *                                             - name (string): New name of the tag
     *                                             - tag_fg (string): New foreground color in hex format (e.g., #FFFFFF)
     *                                             - tag_bg (string): New background color in hex format (e.g., #000000)
     *
     * @throws ConnectionException
     */
    public function update(int|string $spaceId, string $tagName, array $tagObject): Response
    {
        return $this->api->client->put(sprintf('/space/%s/tag/%s', $spaceId, $tagName), ['tag' => $tagObject]);
    }

    /**
     * Delete a task Tag from a Space.
     *
     * @param int|string $spaceId The space ID
     * @param string $tagName The name of the tag to delete
     *
     * @throws ConnectionException
     */
    public function delete(int|string $spaceId, string $tagName): Response
    {
        return $this->api->client->delete(sprintf('/space/%s/tag/%s', $spaceId, $tagName));
    }

    /**
     * Add a Tag to a Task.
     *
     * @param int|string $taskId The task ID
     * @param string $tagName The name of the tag to add
     *
     * @throws ConnectionException
     */
    public function addTagToTask(int|string $taskId, string $tagName, ?bool $customTaskId = null, int|string|null $teamId = null): Response
    {
        if ($customTaskId && empty($teamId)) {
            throw new \InvalidArgumentException('Team ID is required when using a custom task ID.');
        }

        $endpoint = sprintf('/task/%s/tag/%s', $taskId, $tagName);

        if ($customTaskId) {
            return $this->api->client->withQueryParameters([
                'custom_task_ids' => true,
                'team_id'         => $teamId,
            ])->post($endpoint);
        }

        return $this->api->client->post($endpoint);
    }

    /**
     * Remove a Tag from a task. This does not delete the Tag from the Space.
     *
     * @param int|string $taskId The task ID
     * @param string $tagName The name of the tag to add
     *
     * @throws ConnectionException
     */
    public function removeTagFromTask(int|string $taskId, string $tagName, ?bool $customTaskId = null, int|string|null $teamId = null): Response
    {
        if ($customTaskId && empty($teamId)) {
            throw new \InvalidArgumentException('Team ID is required when using a custom task ID.');
        }

        $endpoint = sprintf('/task/%s/tag/%s', $taskId, $tagName);

        if ($customTaskId) {
            return $this->api->client->withQueryParameters([
                'custom_task_ids' => true,
                'team_id'         => $teamId,
            ])->delete($endpoint);
        }

        return $this->api->client->delete($endpoint);
    }
}
