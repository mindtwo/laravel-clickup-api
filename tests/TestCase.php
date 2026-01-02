<?php

namespace Mindtwo\LaravelClickUpApi\Tests;

use Mindtwo\LaravelClickUpApi\ClickUpApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @return array<int, string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ClickUpApiServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}
