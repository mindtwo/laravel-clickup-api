<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class CustomFieldsScope
{
    /**
     * Create a new CustomFieldsScope instance for a specific hierarchy level.
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
     * Get all accessible custom fields at this scope level.
     *
     * @throws ConnectionException
     */
    public function index(): LazyResponseProxy
    {
        $endpoint = sprintf('/%s/%s/field', $this->scopeType, $this->scopeId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }
}
