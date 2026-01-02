<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class AuthorizedUser
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the details of the authenticated user's ClickUp account.
     *
     * @throws ConnectionException
     */
    public function get(): LazyResponseProxy
    {
        $endpoint = '/user';

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }
}
