<?php

namespace Mindtwo\LaravelClickUpApi\Facades;

use Illuminate\Support\Facades\Facade;
use Mindtwo\LaravelClickUpApi\ClickUpClient as BaseClickUpClient;

/**
 * @see \Mindtwo\LaravelClickupApi\ClickUpClient
 */
class ClickUpClient extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BaseClickUpClient::class;
    }
}
