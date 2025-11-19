<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class AuthorizedUser
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the details of the authenticated user's ClickUp account.
     *
     * @throws ConnectionException
     */
    public function get(): Response
    {
        return $this->api->client->asMultipart()->post('/user');
    }
}
