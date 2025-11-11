<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi;

use Mindtwo\LaravelClickUpApi\Commands\ListCustomFieldsCommand;
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
            ->hasConfigFile('clickup');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ClickUpClient::class, function ($app) {
            /** @var string $string */
            $string = config('clickup.api_key');

            return new ClickUpClient($string);
        });
    }
}
