<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Facades;

use Illuminate\Support\Facades\Facade;
use Mindtwo\LaravelClickUpApi\ClickUpClient as BaseClickUpClient;

/**
 * @see BaseClickUpClient
 */
class ClickUpClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseClickUpClient::class;
    }
}
