<?php

namespace Mindtwo\LaravelClickUpApi\Endpoints;

use Mindtwo\LaravelClickUpApi\ClickUpClient;

class Attachment
{
    public function __construct(protected ClickUpClient $api) {}

    public function create(string $taskId, $data)
    {
        return $this->api->client->asMultipart()->post(sprintf('/task/%s/attachment', $taskId), $data);
    }
}
