<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Throwable;

class CheckWebhookHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Execute the job.
     */
    public function handle(Webhooks $webhooksEndpoint): void
    {
        try {
            $workspaceId = config('clickup-api.default_workspace_id');

            if (empty($workspaceId)) {
                Log::info('No workspace found for health check');

                return;
            }

            Log::info(sprintf('Starting webhook health check for workspace: %s', $workspaceId));

            $this->checkWorkspaceWebhooks($workspaceId, $webhooksEndpoint);

            Log::info('Webhook health check completed');
        } catch (Throwable $e) {
            Log::error('Webhook health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check all webhooks for a specific workspace.
     */
    protected function checkWorkspaceWebhooks(int|string $workspaceId, Webhooks $webhooksEndpoint): void
    {
        try {
            $response = $webhooksEndpoint->index($workspaceId);

            if ($response->status() !== 200) {
                Log::warning('Failed to fetch webhooks from ClickUp API', [
                    'workspace_id' => $workspaceId,
                    'status'       => $response->status(),
                ]);

                return;
            }

            $apiWebhooks = $response->json()['webhooks'] ?? [];

            Log::debug('Fetched webhooks from ClickUp API', [
                'workspace_id'  => $workspaceId,
                'webhook_count' => count($apiWebhooks),
            ]);

            foreach ($apiWebhooks as $apiWebhook) {
                $this->syncWebhookHealth($apiWebhook);
            }
        } catch (Throwable $e) {
            Log::error('Failed to check workspace webhooks', [
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync webhook health data from ClickUp API response.
     */
    protected function syncWebhookHealth(array $apiWebhook): void
    {
        $webhook = ClickUpWebhook::where('clickup_webhook_id', $apiWebhook['id'])->first();

        if (! $webhook) {
            // Webhook exists in ClickUp but not in our database - skip
            return;
        }

        $previousStatus = $webhook->health_status;
        $newStatus = WebhookHealthStatus::from($apiWebhook['status'] ?? 'active');
        $failCount = $apiWebhook['fail_count'] ?? 0;

        // Update webhook health data
        $webhook->update([
            'health_status'     => $newStatus,
            'fail_count'        => $failCount,
            'health_checked_at' => now(),
        ]);

        // Handle status changes
        if ($previousStatus !== $newStatus) {
            $this->handleStatusChange($webhook, $previousStatus, $newStatus);
        }
    }

    /**
     * Handle webhook status changes.
     */
    protected function handleStatusChange(ClickUpWebhook $webhook, ?WebhookHealthStatus $previousStatus, WebhookHealthStatus $newStatus): void
    {
        // Log status changes
        Log::warning('ClickUp webhook health status changed', [
            'webhook_id'      => $webhook->clickup_webhook_id,
            'previous_status' => $previousStatus?->value,
            'new_status'      => $newStatus->value,
            'fail_count'      => $webhook->fail_count,
            'endpoint'        => $webhook->endpoint,
            'target_type'     => $webhook->target_type,
            'target_id'       => $webhook->target_id,
        ]);

        // Auto-disable if failing or suspended
        if ($newStatus->needsRecovery()) {
            $webhook->update(['is_active' => false]);

            Log::warning('Webhook auto-disabled due to health status', [
                'webhook_id' => $webhook->clickup_webhook_id,
                'status'     => $newStatus->value,
                'fail_count' => $webhook->fail_count,
            ]);
        }
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new RateLimited('clickup-api-jobs')];
    }
}
