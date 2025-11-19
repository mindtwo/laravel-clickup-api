<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi;

use Mindtwo\LaravelClickUpApi\Commands\ListCustomFieldsCommand;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Attachment;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\AuthorizedUser;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\CustomField;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Folder;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Milestone;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Space;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Subtask;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Task;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskDependency;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskLink;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskList;
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
    }
}
