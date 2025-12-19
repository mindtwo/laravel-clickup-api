<?php

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Support\Collection;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class Webhooks
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the webhooks created via the API for a Workspace. This endpoint returns webhooks created by the authenticated user.
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
     * @param int|string $workspaceId The workspace ID
     * @param array<string, mixed> $data Webhook data including:
     *                    - endpoint (string, required): The URL to send the webhook events to
     *                    - events (array of strings, required): The events to monitor (e.g taskCreated, taskUpdated)
     *                    - space_id (int, optional): The ID of the space to monitor
     *                    - folder_id (int, optional): The ID of the folder to monitor
     *                    - list_id (int, optional): The ID of the list to monitor
     *                    - task_id (int, optional): The ID of the task to monitor
     */
    public function create(int|string $workspaceId, array $data): LazyResponseProxy
    {
        // Auto-set endpoint URL if not provided
        if (! isset($data['endpoint'])) {
            $appUrl = rtrim(config('app.url'), '/');
            $webhookPath = config('clickup-api.webhook.path', '/webhooks/clickup');
            $data['endpoint'] = $appUrl.$webhookPath;
        }

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
     * @param int|string $webhookId The webhook ID
     * @param array<string, mixed> $data Webhook data including:
     *                    - endpoint (string, required): The URL to send the webhook events to
     *                    - events (array of strings, required): The events to monitor (e.g., taskCreated, taskUpdated)
     *                    - status (string, optional): The webhook status (active or inactive)
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

    /**
     * Create a webhook and store it in the database.
     * This method handles both the API call to ClickUp and the local database storage.
     *
     * @param int|string $workspaceId The workspace ID
     * @param array<string, mixed> $data Webhook configuration
     *
     * @return ClickUpWebhook The created webhook model
     */
    public function createManaged(int|string $workspaceId, array $data): ClickUpWebhook
    {
        // Execute API call
        $response = $this->create($workspaceId, $data);

        if ($response->status() !== 200) {
            $error = $response->json()['err'] ?? 'Unknown error';
            throw new RuntimeException("ClickUp Api call failed: {$error}");
        }

        $response = $response->json();

        // Extract webhook data from response
        $webhookData = $response['webhook'] ?? $response;

        // Determine target type and ID
        [$targetType, $targetId] = $this->determineTarget($workspaceId, $data);

        // Store in database
        return ClickUpWebhook::create([
            'clickup_webhook_id' => $webhookData['id'],
            'endpoint'           => $webhookData['endpoint'] ?? $data['endpoint'],
            'event'              => is_array($data['events']) ? implode(',', $data['events']) : $data['events'],
            'target_type'        => $targetType,
            'target_id'          => $targetId,
            'secret'             => $webhookData['secret'] ?? null,
            'is_active'          => true,
        ]);
    }

    /**
     * Update a webhook in ClickUp and sync to database.
     *
     * @param int|string $webhookId The webhook ID
     * @param array<string, mixed> $data Webhook data to update
     */
    public function updateManaged(int|string $webhookId, array $data): ClickUpWebhook|null
    {
        // Execute API call
        $response = $this->update($webhookId, $data);

        if ($response->status() !== 200) {
            $error = $response->json()['err'] ?? 'Unknown error';
            throw new RuntimeException("ClickUp Api call failed: {$error}");
        }

        // Find and update local webhook
        $webhook = ClickUpWebhook::where('clickup_webhook_id', $webhookId)->firstOrFail();

        $updateData = [];

        if (isset($data['endpoint'])) {
            $updateData['endpoint'] = $data['endpoint'];
        }

        if (isset($data['events'])) {
            $updateData['event'] = is_array($data['events'])
                ? implode(',', $data['events'])
                : $data['events'];
        }

        if (! empty($updateData)) {
            $webhook->update($updateData);
        }

        return $webhook->fresh();
    }

    /**
     * Delete a webhook from ClickUp and mark as deleted in database.
     */
    public function deleteManaged(int|string $webhookId): bool
    {
        // Execute API call
        $response = $this->delete($webhookId);

        if ($response->status() !== 200) {
            $error = $response->json()['err'] ?? 'Unknown error';
            throw new RuntimeException("ClickUp Api call failed: {$error}");
        }

        // Soft delete from database
        $webhook = ClickUpWebhook::where('clickup_webhook_id', $webhookId)->first();

        if ($webhook) {
            $webhook->delete();
        }

        return true;
    }

    /**
     * Fetch webhooks from ClickUp API and sync with database.
     *
     * @param int|string $workspaceId The workspace ID
     * @return Collection<int, ClickUpWebhook> The collection of synced webhooks
     */
    public function syncFromApi(int|string $workspaceId): Collection
    {
        // Fetch from API
        $response = $this->index($workspaceId);

        if ($response->status() !== 200) {
            $error = $response->json()['err'] ?? 'Unknown error';
            throw new RuntimeException("ClickUp Api call failed: {$error}");
        }

        $responseData = $response->json();

        $apiWebhooks = $responseData['webhooks'] ?? [];

        $synced = collect();

        foreach ($apiWebhooks as $apiWebhook) {
            // Determine target from API webhook data
            [$targetType, $targetId] = $this->determineTarget($workspaceId, $apiWebhook);

            $webhook = ClickUpWebhook::updateOrCreate(
                ['clickup_webhook_id' => $apiWebhook['id']],
                [
                    'endpoint' => $apiWebhook['endpoint'],
                    'event'    => is_array($apiWebhook['events'])
                        ? implode(',', $apiWebhook['events'])
                        : ($apiWebhook['events'][0] ?? '*'),
                    'target_type'       => $targetType,
                    'target_id'         => $targetId,
                    'is_active'         => ($apiWebhook['health']['status'] !== 'suspended'),
                    'health_status'     => WebhookHealthStatus::from($apiWebhook['health']['status'] ?? 'active'),
                    'fail_count'        => $apiWebhook['health']['fail_count'] ?? 0,
                    'health_checked_at' => now(),
                ]
            );

            $synced->push($webhook);
        }

        return $synced;
    }

    /**
     * Determine the target type and ID from webhook data.
     *
     * @param int|string $workspaceId
     * @param array<string, mixed> $data
     *
     * @return array<int, mixed> [targetType, targetId]
     */
    private function determineTarget(int|string $workspaceId, array $data): array
    {
        if (isset($data['task_id'])) {
            return ['task', (string) $data['task_id']];
        }

        if (isset($data['list_id'])) {
            return ['list', (string) $data['list_id']];
        }

        if (isset($data['folder_id'])) {
            return ['folder', (string) $data['folder_id']];
        }

        if (isset($data['space_id'])) {
            return ['space', (string) $data['space_id']];
        }

        return ['workspace', (string) $workspaceId];
    }
}
