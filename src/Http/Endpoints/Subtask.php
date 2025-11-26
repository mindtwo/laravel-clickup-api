<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;
class Subtask
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Create a subtask under a parent task.
     *
     * @param int|string $listId The list ID where the task will be created
     * @param int|string $parentTaskId The parent task ID
     * @param array<string, int|string> $data Task data including:
     *                                        - name (string, required): Subtask name
     *                                        - description (string, optional): Subtask description
     *                                        - assignees (array, optional): Array of user IDs
     *                                        - status (string, optional): Task status
     *                                        - priority (int, optional): Priority level (1-4)
     *                                        - due_date (int, optional): Due date in Unix milliseconds
     *                                        - due_date_time (bool, optional): Include time in due date
     *                                        - time_estimate (int, optional): Time in milliseconds
     *                                        - tags (array, optional): Array of tag names
     *                                        - custom_fields (array, optional): Custom field values
     *
     * @throws ConnectionException
     * @throws InvalidArgumentException
     */
    public function create(int|string $listId, int|string $parentTaskId, array $data): LazyResponseProxy
    {
        // Validate that the parent parameter is not already set
        if (isset($data['parent'])) {
            throw new InvalidArgumentException(
                'The parent parameter is automatically set. Do not include it in the data array.'
            );
        }

        // Validate that required name field is present
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Subtask name is required.');
        }

        // Add the parent task ID to the data
        $data['parent'] = (string) $parentTaskId;

        $endpoint = sprintf('/list/%s/task', $listId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Get all subtasks of a parent task.
     *
     * Note: This retrieves the parent task and extracts its subtasks from the response.
     *
     * @param int|string $parentTaskId The parent task ID
     *
     * @throws ConnectionException
     */
    public function index(int|string $parentTaskId): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s', $parentTaskId);
        $queryParams = ['include_subtasks' => true];

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET',
            queryParams: $queryParams
        );
    }

    /**
     * Update a subtask.
     *
     * Note: Subtasks are updated the same way as regular tasks.
     * Use the Task endpoints' update method, or this convenience method.
     *
     * @param int|string $subtaskId The subtask ID
     * @param array<string, int|string> $data Data to update
     *
     * @throws ConnectionException
     */
    public function update(int|string $subtaskId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s', $subtaskId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'PUT',
            body: $data
        );
    }

    /**
     * Delete a subtask.
     *
     * Note: Subtasks are deleted the same way as regular tasks.
     * Use the Task endpoints' delete method, or this convenience method.
     *
     * @param int|string $subtaskId The subtask ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $subtaskId): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s', $subtaskId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE'
        );
    }
}
