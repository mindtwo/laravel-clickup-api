<?php

namespace Mindtwo\LaravelClickUpApi\Endpoints;

use Mindtwo\LaravelClickUpApi\ClickUpClient;

class CustomField
{
    public function __construct(protected ClickUpClient $api) {}

    public function show(string $listId)
    {
        return $this->api->client->get(sprintf('/list/%s/field', $listId));
    }
}
