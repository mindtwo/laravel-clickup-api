<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class Workspaces
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the Workspaces available to the authenticated user.
     *
     * @throws ConnectionException
     */
    public function get(): LazyResponseProxy
    {
        $endpoint = '/team';

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * View the used, total, and available member and guest seats for a Workspace.
     *
     * @throws ConnectionException
     */
    public function seats(string $team_id): LazyResponseProxy
    {
        $endpoint = sprintf('/teams/%s/seats', $team_id);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * View the current Plan for the specified Workspace.
     *
     * @throws ConnectionException
     */
    public function plan(string $team_id): LazyResponseProxy
    {
        $endpoint = sprintf('/teams/%s/plan', $team_id);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }
}
