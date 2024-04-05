<?php

namespace Mindtwo\LaravelClickUpApi\Tests;

use Mindtwo\LaravelClickUpApi\ClickUpApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ClickUpApiServiceProvider::class,
        ];
    }
}
