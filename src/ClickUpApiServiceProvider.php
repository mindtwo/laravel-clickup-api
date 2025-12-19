<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Mindtwo\LaravelClickUpApi\Commands\ListCustomFieldsCommand;
use Mindtwo\LaravelClickUpApi\Commands\RecoverWebhookCommand;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Attachment;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\AuthorizedUser;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\CustomField;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Folder;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Milestone;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Space;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Subtask;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Tag;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Task;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskDependency;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskLink;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskList;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Views;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Workspaces;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ClickUpApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-clickup-api')
            ->hasCommand(ListCustomFieldsCommand::class)
            ->hasCommand(RecoverWebhookCommand::class)
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        // Register the main ClickUp client
        $this->app->singleton(ClickUpClient::class, function ($app) {
            /** @var string $string */
            $string = config('clickup-api.api_key');

            return new ClickUpClient($string);
        });

        // Register all endpoint classes as singletons
        $this->app->singleton(Task::class);
        $this->app->singleton(Space::class);
        $this->app->singleton(Folder::class);
        $this->app->singleton(TaskList::class);
        $this->app->singleton(CustomField::class);
        $this->app->singleton(Attachment::class);
        $this->app->singleton(Subtask::class);
        $this->app->singleton(Milestone::class);
        $this->app->singleton(TaskDependency::class);
        $this->app->singleton(TaskLink::class);
        $this->app->singleton(AuthorizedUser::class);
        $this->app->singleton(Workspaces::class);
        $this->app->singleton(Webhooks::class);
        $this->app->singleton(Views::class);
        $this->app->singleton(Tag::class);
    }

    public function packageBooted(): void
    {
        // Add ratelimiter for api call jobs
        RateLimiter::for('clickup-api-jobs', function (object $job) {
            return Limit::perMinute(config('clickup-api.rate_limit_per_minute'))->by('clickup-api-jobs');
        });

        // Register webhook routes
        if (config('clickup-api.webhook.enabled', true)) {
            $this->registerWebhookRoutes();
        }

        // Register event logging listener
        $this->registerEventLogging();

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'clickup-api-migrations');
    }

    /**
     * Register the webhook routes.
     */
    protected function registerWebhookRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    /**
     * Register the event logging listener.
     */
    protected function registerEventLogging(): void
    {
        // Only register if logging is enabled
        if (! config('clickup-api.logging.enabled', false)) {
            return;
        }

        Event::listen(
            Events\ClickUpEvent::class,
            Listeners\LogClickUpEvent::class
        );
    }

    /**
     * Validate required configuration values.
     */
    protected function validateConfiguration(): void
    {
        // Check if API key is configured
        $apiKey = config('clickup-api.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'ClickUp API key is not configured. '.
                'Please set the CLICKUP_API_KEY environment variable or publish the config file.'
            );
        }

        // Validate rate limit is a positive integer
        $rateLimit = config('clickup-api.rate_limit_per_minute', 100);

        if (! is_int($rateLimit) || $rateLimit <= 0) {
            throw new \RuntimeException(
                'ClickUp API rate limit must be a positive integer. '.
                "Current value: {$rateLimit}"
            );
        }
    }
}
