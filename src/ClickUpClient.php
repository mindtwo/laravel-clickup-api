<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ClickUpClient
{
    public PendingRequest $client;

    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.clickup.com/api/v2'
    ) {
        $this->client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => $this->apiKey,
            ]);
    }
}
