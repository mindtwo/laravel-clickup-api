<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class TaskDependency
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Add a "waiting on" dependency.
     *
     * This makes the specified task wait for another task to complete.
     * Example: Task A depends on Task B (Task A is waiting for Task B to finish).
     *
     * @param int|string $taskId The task that will wait
     * @param int|string $dependsOnTaskId The task that must be completed first
     *
     * @throws ConnectionException
     */
    public function addDependsOn(int|string $taskId, int|string $dependsOnTaskId): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s/dependency', $taskId);
        $data = ['depends_on' => (string) $dependsOnTaskId];

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Add a "blocking" dependency.
     *
     * This makes the specified task block another task from starting.
     * Example: Task A blocks Task B (Task B must wait for Task A to finish).
     *
     * @param int|string $taskId The task that blocks
     * @param int|string $blockedTaskId The task that is blocked
     *
     * @throws ConnectionException
     */
    public function addBlocking(int|string $taskId, int|string $blockedTaskId): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s/dependency', $taskId);
        $data = ['dependency_of' => (string) $blockedTaskId];

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Add a dependency (flexible method).
     *
     * Use this method when you want more control over the dependency data.
     * You must provide either 'depends_on' or 'dependency_of' in the data array.
     *
     * @param int|string $taskId The task ID
     * @param array<string, int|string> $data Dependency data containing either:
     *                                        - depends_on (string): Task ID this task depends on
     *                                        - dependency_of (string): Task ID that this task blocks
     *
     * @throws InvalidArgumentException
     * @throws ConnectionException
     */
    public function add(int|string $taskId, array $data): LazyResponseProxy
    {
        // Validate that exactly one of depends_on or dependency_of is provided
        $hasDependsOn = isset($data['depends_on']);
        $hasDependencyOf = isset($data['dependency_of']);

        if (! $hasDependsOn && ! $hasDependencyOf) {
            throw new InvalidArgumentException(
                'Must specify either "depends_on" or "dependency_of" in the data array.'
            );
        }

        if ($hasDependsOn && $hasDependencyOf) {
            throw new InvalidArgumentException(
                'Cannot specify both "depends_on" and "dependency_of". Use only one.'
            );
        }

        $endpoint = sprintf('/task/%s/dependency', $taskId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Delete a dependency.
     *
     * Remove a dependency relationship between two tasks.
     *
     * @param int|string $taskId The task ID
     * @param array<string, int|string> $data Dependency data containing either:
     *                                        - depends_on (string): Task ID to remove from depends_on
     *                                        - dependency_of (string): Task ID to remove from dependency_of
     *
     * @throws ConnectionException
     */
    public function delete(int|string $taskId, array $data): LazyResponseProxy
    {
        // Build query parameters from data
        $query = [];
        if (isset($data['depends_on'])) {
            $query['depends_on'] = $data['depends_on'];
        }
        if (isset($data['dependency_of'])) {
            $query['dependency_of'] = $data['dependency_of'];
        }

        $endpoint = sprintf('/task/%s/dependency', $taskId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE',
            queryParams: $query
        );
    }
}
