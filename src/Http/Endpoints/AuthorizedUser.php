<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

class AuthorizedUser
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * View the details of the authenticated user's ClickUp account.
     *
     * @throws ConnectionException
     */
    public function get(): Response|ClickUpApiCallJob
    {
        $endpoint = '/user';

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }
}
