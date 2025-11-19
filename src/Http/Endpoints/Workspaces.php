<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Workspaces
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the Workspaces available to the authenticated user.
     *
     * @throws ConnectionException
     */
    public function get(): Response
    {
        return $this->api->client->get('/team');
    }

    /**
     * View the used, total, and available member and guest seats for a Workspace.
     *
     * @throws ConnectionException
     */
    public function seats(string $team_id): Response
    {
        return $this->api->client->get(sprintf('/teams/%s/seats', $team_id));
    }

    /**
     * View the current Plan for the specified Workspace.
     *
     * @throws ConnectionException
     */
    public function plan(string $team_id): Response
    {
        return $this->api->client->get(sprintf('/teams/%s/plan', $team_id));
    }
}
