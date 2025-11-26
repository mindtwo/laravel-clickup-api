<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

class Workspaces
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the Workspaces available to the authenticated user.
     *
     * @throws ConnectionException
     */
    public function get(): Response|ClickUpApiCallJob
    {
        $endpoint = '/team';

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * View the used, total, and available member and guest seats for a Workspace.
     *
     * @throws ConnectionException
     */
    public function seats(string $team_id): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/teams/%s/seats', $team_id);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * View the current Plan for the specified Workspace.
     *
     * @throws ConnectionException
     */
    public function plan(string $team_id): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/teams/%s/plan', $team_id);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }
}
