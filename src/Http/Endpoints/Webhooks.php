<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
     *                                   - endpoint (string, required): The URL to send the webhook events to
     *                                   - events (array of strings, required): The events to monitor (e.g taskCreated, taskUpdated)
     *                                   - space_id (int, optional): The ID of the space to monitor
     *                                   - folder_id (int, optional): The ID of the folder to monitor
     *                                   - list_id (int, optional): The ID of the list to monitor
     *                                   - task_id (int, optional): The ID of the task to monitor
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
     *                                   - endpoint (string, required): The URL to send the webhook events to
     *                                   - events (array of strings, required): The events to monitor (e.g., taskCreated, taskUpdated)
     *                                   - status (string, optional): The webhook status (active or inactive)
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
    public function updateManaged(int|string $webhookId, array $data): ?ClickUpWebhook
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
     *
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
     * Ensure the desired set of managed webhooks exists and is active in ClickUp.
     *
     * For every desired specification this guarantees an active webhook covering
     * the given target and events by, in order of preference:
     *   1. leaving an already-active matching webhook untouched,
     *   2. reactivating a matching webhook that exists but is inactive,
     *   3. recreating it when ClickUp no longer knows the webhook (update 404),
     *   4. creating a brand-new webhook when none covers the target + events.
     *
     * This closes the gap where a required event-type webhook (e.g. taskDeleted)
     * is silently dropped or suspended and never restored, which leaves the
     * application blind to those events.
     *
     * @param int|string $workspaceId The workspace the webhooks belong to
     * @param array<int, array<string, mixed>> $desired List of specs, each with an
     *                                                  `events` array plus optional `endpoint`
     *                                                  and a target key (space_id/folder_id/list_id/task_id)
     *
     * @return Collection<int, ClickUpWebhook> The ensured webhooks
     */
    public function ensureManaged(int|string $workspaceId, array $desired): Collection
    {
        $ensured = collect();

        foreach ($desired as $spec) {
            /** @var array<int, string> $events */
            $events = array_values(array_unique($spec['events'] ?? []));

            if (empty($events)) {
                continue;
            }

            [$targetType, $targetId] = $this->determineTarget($workspaceId, $spec);

            $existing = $this->findManagedWebhook($targetType, (string) $targetId, $events);

            if ($existing && $existing->is_active && $existing->health_status === WebhookHealthStatus::ACTIVE) {
                $ensured->push($existing);

                continue;
            }

            if ($existing) {
                $ensured->push($this->reactivateOrRecreate($workspaceId, $existing, $spec, $events));

                continue;
            }

            Log::info('Creating missing managed ClickUp webhook', [
                'target_type' => $targetType,
                'target_id'   => (string) $targetId,
                'events'      => $events,
            ]);

            $ensured->push($this->createManaged($workspaceId, $spec));
        }

        return $ensured;
    }

    /**
     * Find a managed webhook for the given target whose event list covers all desired events.
     *
     * @param array<int, string> $events
     */
    private function findManagedWebhook(string $targetType, string $targetId, array $events): ?ClickUpWebhook
    {
        /** @var ClickUpWebhook */
        return ClickUpWebhook::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->get()
            ->first(function (ClickUpWebhook $webhook) use ($events): bool {
                $covered = array_filter(array_map('trim', explode(',', (string) $webhook->event)));

                // A wildcard webhook covers every event type.
                if (in_array('*', $covered, true)) {
                    return true;
                }

                return empty(array_diff($events, $covered));
            });
    }

    /**
     * Reactivate an inactive webhook, or recreate it when ClickUp has dropped it.
     *
     * @param array<string, mixed> $spec
     * @param array<int, string> $events
     */
    private function reactivateOrRecreate(int|string $workspaceId, ClickUpWebhook $webhook, array $spec, array $events): ClickUpWebhook
    {
        $endpoint = $spec['endpoint'] ?? $webhook->endpoint ?? $this->defaultEndpoint();

        // A managed row without an upstream ID was never really registered - recreate it.
        if (empty($webhook->clickup_webhook_id)) {
            $webhook->delete();

            return $this->createManaged($workspaceId, $spec);
        }

        $response = $this->update($webhook->clickup_webhook_id, [
            'endpoint' => $endpoint,
            'events'   => $events,
            'status'   => 'active',
        ]);

        if ($response->status() === 200) {
            $webhook->update([
                'endpoint'      => $endpoint,
                'event'         => implode(',', $events),
                'health_status' => WebhookHealthStatus::ACTIVE,
                'is_active'     => true,
                'fail_count'    => 0,
            ]);

            Log::info('Reactivated managed ClickUp webhook', [
                'webhook_id' => $webhook->clickup_webhook_id,
                'events'     => $events,
            ]);

            $webhook->refresh();

            return $webhook;
        }

        if ($response->status() === 404) {
            // The webhook no longer exists in ClickUp - drop the stale row and recreate it.
            Log::warning('Managed ClickUp webhook missing upstream, recreating', [
                'webhook_id' => $webhook->clickup_webhook_id,
                'events'     => $events,
            ]);

            $webhook->delete();

            return $this->createManaged($workspaceId, $spec);
        }

        $error = $response->json()['err'] ?? 'Unknown error';
        throw new RuntimeException("ClickUp Api call failed: {$error}");
    }

    /**
     * Build the default webhook endpoint URL from configuration.
     */
    private function defaultEndpoint(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $webhookPath = config('clickup-api.webhook.path', '/webhooks/clickup');

        return $appUrl.$webhookPath;
    }

    /**
     * Determine the target type and ID from webhook data.
     *
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
