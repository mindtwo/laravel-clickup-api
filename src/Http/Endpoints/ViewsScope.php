<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use Symfony\Component\HttpFoundation\Request;

class ViewsScope
{
    /**
     * Create a new ViewsScope instance for a specific hierarchy level.
     *
     * @param ClickUpClient $api The ClickUp API client
     * @param string $scopeType The scope type ('team', 'space', 'folder', 'list')
     * @param int|string $scopeId The ID of the scope resource
     */
    public function __construct(
        protected ClickUpClient $api,
        protected string $scopeType,
        protected int|string $scopeId
    ) {}

    /**
     * Get all views at this scope level.
     *
     * @throws ConnectionException
     */
    public function index(): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/%s/%s/view', $this->scopeType, $this->scopeId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_GET,
            );
        }

        return $this->api->client->get($endpoint);
    }

    /**
     * Create a new view at this scope level.
     *
     * @param array<string, mixed> $data View data including:
     *                                   - name (string, required): View name
     *                                   - type (string, required): View type (list, board, calendar, etc.)
     *                                   - grouping (object, optional): Grouping configuration
     *                                   - divide (int, optional): Division setting
     *                                   - sorting (object, optional): Sorting configuration
     *                                   - filters (object, optional): Filter configuration
     *                                   - columns (object, optional): Column configuration
     *                                   - team_sidebar (object, optional): Team sidebar configuration
     *                                   - settings (object, optional): Additional view settings
     *
     * @throws ConnectionException
     */
    public function create(array $data): Response|ClickUpApiCallJob
    {
        $endpoint = sprintf('/%s/%s/view', $this->scopeType, $this->scopeId);

        if (config('clickup-api.queue')) {
            return new ClickUpApiCallJob(
                endpoint: $endpoint,
                method: Request::METHOD_POST,
                body: $data,
            );
        }

        return $this->api->client->post($endpoint, $data);
    }
}
