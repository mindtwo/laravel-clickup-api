<?php

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;
use Symfony\Component\HttpFoundation\Request;

class Webhooks
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the webhooks created via the API for a Workspace. This endpoint returns webhooks created by the authenticated user.
     *
     * @param int|string $workspaceId
     * @return LazyResponseProxy
     */
    public function index(int|string $workspaceId): LazyResponseProxy
    {
        $endpoint = sprintf('/team/%s/webhook', $workspaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: Request::METHOD_GET
        );
    }

    /**
     * Set up a webhook to monitor for events.
     * We do not have a dedicated IP address for webhooks.
     *
     * @param int|string $workspaceId
     * @param array $data Webhook data including:
     *                      - endpoint (string, required): The URL to send the webhook events to
     *                      - events (array of strings, required): The events to monitor (e.g taskCreated, taskUpdated)
     *                      - space_id (int, optional): The ID of the space to monitor
     *                      - folder_id (int, optional): The ID of the folder to monitor
     *                      - list_id (int, optional): The ID of the list to monitor
     *                      - task_id (int, optional): The ID of the task to monitor
     * @return LazyResponseProxy
     */
    public function create(int|string $workspaceId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/team/%s/webhook', $workspaceId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: Request::METHOD_POST,
            body: $data
        );
    }

    /**
     * Update a webhook to change the events to be monitored.
 *
     * @param int|string $webhookId
     * @param array $data Webhook data including:
     *                   - endpoint (string, required): The URL to send the webhook events to
     *                   - events (array of strings, required): The events to monitor (e.g., taskCreated, taskUpdated)
     *                   - status (string, optional): The webhook status (active or inactive)
     * @return LazyResponseProxy
     */
    public function update(int|string $webhookId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/webhook/%s', $webhookId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: Request::METHOD_PUT,
            body: $data
        );
    }

    /**
     * Delete a webhook.
     *
     * @param int|string $webhookId
     * @return LazyResponseProxy
     */
    public function delete(int|string $webhookId): LazyResponseProxy
    {
        $endpoint = sprintf('/webhook/%s', $webhookId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: Request::METHOD_DELETE
        );
    }
}
