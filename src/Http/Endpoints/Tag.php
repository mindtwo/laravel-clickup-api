<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

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
    public function index(int|string $spaceId): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s/tag', $spaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
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
    public function create(int|string $listId, array $tagObject): LazyResponseProxy
    {
        // Validate immediately (regardless of queue setting)
        if (empty($tagObject['name'])) {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        if (empty($tagObject['tag_fg'])) {
            throw new \InvalidArgumentException('Tag foreground color (tag_fg) is required.');
        }

        if (empty($tagObject['tag_bg'])) {
            throw new \InvalidArgumentException('Tag background color (tag_bg) is required.');
        }

        $endpoint = sprintf('/space/%s/tag', $listId);
        $body = ['tag' => $tagObject];

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $body
        );
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
    public function update(int|string $spaceId, string $tagName, array $tagObject): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s/tag/%s', $spaceId, $tagName);
        $body = ['tag' => $tagObject];

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'PUT',
            body: $body
        );
    }

    /**
     * Delete a task Tag from a Space.
     *
     * @param int|string $spaceId The space ID
     * @param string $tagName The name of the tag to delete
     *
     * @throws ConnectionException
     */
    public function delete(int|string $spaceId, string $tagName): LazyResponseProxy
    {
        $endpoint = sprintf('/space/%s/tag/%s', $spaceId, $tagName);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE'
        );
    }

    /**
     * Add a Tag to a Task.
     *
     * @param int|string $taskId The task ID
     * @param string $tagName The name of the tag to add
     *
     * @throws ConnectionException
     */
    public function addTagToTask(int|string $taskId, string $tagName, ?bool $customTaskId = null, int|string|null $teamId = null): LazyResponseProxy
    {
        if ($customTaskId && empty($teamId)) {
            throw new \InvalidArgumentException('Team ID is required when using a custom task ID.');
        }

        $endpoint = sprintf('/task/%s/tag/%s', $taskId, $tagName);
        $queryParams = [];

        if ($customTaskId) {
            $queryParams = [
                'custom_task_ids' => true,
                'team_id'         => $teamId,
            ];
        }

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            queryParams: $queryParams
        );
    }

    /**
     * Remove a Tag from a task. This does not delete the Tag from the Space.
     *
     * @param int|string $taskId The task ID
     * @param string $tagName The name of the tag to add
     *
     * @throws ConnectionException
     */
    public function removeTagFromTask(int|string $taskId, string $tagName, ?bool $customTaskId = null, int|string|null $teamId = null): LazyResponseProxy
    {
        if ($customTaskId && empty($teamId)) {
            throw new \InvalidArgumentException('Team ID is required when using a custom task ID.');
        }

        $endpoint = sprintf('/task/%s/tag/%s', $taskId, $tagName);
        $queryParams = [];

        if ($customTaskId) {
            $queryParams = [
                'custom_task_ids' => true,
                'team_id'         => $teamId,
            ];
        }

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE',
            queryParams: $queryParams
        );
    }
}
