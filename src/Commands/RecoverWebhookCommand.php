<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class RecoverWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clickup:webhook-recover {webhook_id? : The ClickUp webhook ID to recover} {--all: Recover all failed/suspended webhooks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually reactivate failed or suspended ClickUp webhooks';

    /**
     * Execute the console command.
     */
    public function handle(Webhooks $webhooksEndpoint): int
    {
        /** @var null|string $webhookId */
        $webhookId = $this->argument('webhook_id');
        $all = $this->option('all');

        if (! $all && ! $webhookId) {
            $this->error('Please provide either a webhook_id or use the --all flag');

            return CommandAlias::INVALID;
        }

        if ($all) {
            return $this->recoverAllWebhooks($webhooksEndpoint);
        }

        return $this->recoverSingleWebhook($webhookId, $webhooksEndpoint);
    }

    /**
     * Recover all failed/suspended webhooks.
     */
    protected function recoverAllWebhooks(Webhooks $webhooksEndpoint): int
    {
        $webhooks = ClickUpWebhook::whereIn('health_status', [
            WebhookHealthStatus::FAILING,
            WebhookHealthStatus::SUSPENDED,
        ])->where('is_active', false)->get();

        if ($webhooks->isEmpty()) {
            $this->info('No webhooks need recovery.');

            return CommandAlias::SUCCESS;
        }

        $this->info("Found {$webhooks->count()} webhook(s) to recover.");
        $this->newLine();

        $successful = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            if ($this->recoverWebhook($webhook, $webhooksEndpoint)) {
                $successful++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Recovery complete: {$successful} successful, {$failed} failed");

        return $failed > 0 ? CommandAlias::FAILURE : CommandAlias::SUCCESS;
    }

    /**
     * Recover a single webhook by ID.
     */
    protected function recoverSingleWebhook(string $webhookId, Webhooks $webhooksEndpoint): int
    {
        $webhook = ClickUpWebhook::where('clickup_webhook_id', $webhookId)->first();

        if (! $webhook) {
            $this->error("Webhook not found: {$webhookId}");

            return CommandAlias::FAILURE;
        }

        $success = $this->recoverWebhook($webhook, $webhooksEndpoint);

        return $success ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
    }

    /**
     * Attempt to recover a single webhook.
     */
    protected function recoverWebhook(ClickUpWebhook $webhook, Webhooks $webhooksEndpoint): bool
    {
        $this->info("Attempting to recover webhook: {$webhook->clickup_webhook_id}");
        $this->line("  Status: {$webhook->health_status->value}");
        $this->line("  Endpoint: {$webhook->endpoint}");
        $this->line("  Fail count: {$webhook->fail_count}");

        try {
            // Reactivate via ClickUp API
            $response = $webhooksEndpoint->update($webhook->clickup_webhook_id, [
                'endpoint' => $webhook->endpoint,
                'events'   => explode(',', $webhook->event),
                'status'   => 'active',
            ]);

            if ($response->status() === 200) {
                $webhook->update([
                    'health_status' => WebhookHealthStatus::ACTIVE,
                    'is_active'     => true,
                    'fail_count'    => 0,
                ]);

                $this->info("  ✓ Successfully recovered webhook {$webhook->clickup_webhook_id}");

                Log::info('Webhook manually recovered via command', [
                    'webhook_id' => $webhook->clickup_webhook_id,
                    'endpoint'   => $webhook->endpoint,
                ]);

                return true;
            }

            $error = $response->json()['err'] ?? 'Unknown error';
            $this->error("  ✗ Failed to recover webhook: {$error}");

            return false;
        } catch (Throwable $e) {
            $this->error("  ✗ Error recovering webhook: {$e->getMessage()}");

            Log::error('Webhook recovery command failed', [
                'webhook_id' => $webhook->clickup_webhook_id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }
}
