<?php

namespace Mindtwo\LaravelClickUpApi\Endpoints;

use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Task
{
    public function __construct(protected ClickUpClient $api)
    {
    }

    public function index(int|string $listId, array $data)
    {
        return $this->api->client->get(sprintf('/list/%s/task', $listId), $data);
    }

    public function show($taskId)
    {
        return $this->api->client->get(sprintf('/task/%s', $taskId));
    }

    public function create(int|string $listId, array $data)
    {
        return $this->api->client->post(sprintf('/list/%s/task', $listId), $data);
    }

    public function update(int|string $taskId, array $data)
    {
        return $this->api->client->put(sprintf('/task/%s', $taskId), $data);
    }

    public function delete(int|string $taskId)
    {
        return $this->api->client->delete(sprintf('/task/%s', $taskId));
    }
}
