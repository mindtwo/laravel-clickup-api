<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

class Task
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get all tasks in a list.
     *
     * @param int|string $listId The list ID
     * @param array<string, int|string> $data Query parameters for filtering tasks (page, order_by, subtasks, etc.)
     *
     * @throws ConnectionException
     */
    public function index(int|string $listId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/list/%s/task', $listId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
                queryParams: $data,
            );
        }

        return $this->api->client->get($endpoint, $data);
    }

    /**
     * Get a single task by ID.
     *
     * @param int|string $taskId The task ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $taskId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/task/%s', $taskId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Create a new task in a list.
     *
     * @param int|string $listId The list ID
     * @param array<string, int|string> $data Task data including:
     *                                        - name (string, required): Task name
     *                                        - description (string, optional): Task description
     *                                        - assignees (array, optional): Array of user IDs
     *                                        - status (string, optional): Task status
     *                                        - priority (int, optional): Priority level (1-4)
     *                                        - due_date (int, optional): Due date in Unix milliseconds
     *                                        - tags (array, optional): Array of tag names
     *
     * @throws ConnectionException
     */
    public function create(int|string $listId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/list/%s/task', $listId);

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
     * Update an existing task.
     *
     * @param int|string $taskId The task ID
     * @param array<string, int|string> $data Task data to update (same as create)
     *
     * @throws ConnectionException
     */
    public function update(int|string $taskId, array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/task/%s', $taskId);

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
     * Delete a task.
     *
     * @param int|string $taskId The task ID
     *
     * @throws ConnectionException
     */
    public function delete(int|string $taskId): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/task/%s', $taskId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_DELETE,
            );
        }

        return $this->api->client->delete($endpoint);
    }

    /**
     * Create a milestone task.
     *
     * This is a convenience method for creating tasks with a milestone custom type.
     * You must provide the milestone type ID, which can be obtained using the Milestone endpoint.
     *
     * @param int|string $listId The list ID
     * @param string $name The milestone name
     * @param int|string $customTypeId The milestone custom task type ID
     * @param array<string, int|string> $additionalData Optional additional task data (description, assignees, due_date, etc.)
     *
     * @throws ConnectionException
     */
    public function createMilestone(int|string $listId, string $name, int|string $customTypeId, array $additionalData = []): Response|ClickUpApiCallJob
    {
        $data = array_merge(
            [
                'name'        => $name,
                'custom_type' => (string) $customTypeId,
            ],
            $additionalData
        );

        return $this->create($listId, $data);
    }

    /**
     * Get a task with its relationship data parsed out.
     *
     * This convenience method retrieves a task and returns its data along with
     * dependencies and linked tasks in a structured format.
     *
     * This method is not queable and will always perform a direct API call.
     *
     * @param int|string $taskId The task ID
     *
     * @throws ConnectionException
     *
     * @return array<string, array<string, array<string, int|string>|int|string>|int|string> Contains 'task',
     *                                                                                       'dependencies', and
     *                                                                                       'linked_tasks' keys
     */
    public function showWithRelationships(int|string $taskId): array
    {
        /** @var Response $response */
        $response = $this->show($taskId);

        /**
         * @var array<string, array<string, int|string>|int|string> $task Contains 'task', 'dependencies',
         *                                                          'fields', 'custom_items', and 'linked_tasks' keys
         */
        $task = $response->json();

        return [
            'task'         => $task,
            'dependencies' => $task['dependencies'] ?? [],
            'linked_tasks' => $task['linked_tasks'] ?? [],
        ];
    }
}
