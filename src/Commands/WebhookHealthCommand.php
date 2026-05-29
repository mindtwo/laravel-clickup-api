<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Commands;

use Illuminate\Console\Command;
use Mindtwo\LaravelClickUpApi\Jobs\CheckWebhookHealth;

class WebhookHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clickup:webhook-health
                            {--sync : Run the health check inline instead of dispatching to the queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync ClickUp webhook health and, when auto-restore is enabled, recreate missing required webhooks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('sync')) {
            CheckWebhookHealth::dispatchSync();
            $this->info('Webhook health check completed (sync).');

            return self::SUCCESS;
        }

        CheckWebhookHealth::dispatch();
        $this->info('Webhook health check dispatched to the queue.');

        return self::SUCCESS;
    }
}
