<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Events\ClickUpApiCallCompleted;
use Symfony\Component\HttpFoundation\Request;

class ClickUpApiCallJob implements ShouldQueue
{
    use Batchable,
        Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        public string $endpoint,
        public string $method,
        public array $body = [],
        public array $queryParams = [],
        public array $options = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Resolve ClickUpClient from container
        $api = app(ClickUpClient::class);
        $client = $api->client;

        // Handle multipart requests
        if (isset($this->options['multipart']) && $this->options['multipart'] === true) {
            $client = $client->asMultipart();
        }

        // Handle query parameters
        if (! empty($this->queryParams)) {
            $client = $client->withQueryParameters($this->queryParams);
        }

        // Execute the request based on HTTP method
        $response = match ($this->method) {
            Request::METHOD_GET    => $client->get($this->endpoint, $this->queryParams),
            Request::METHOD_POST   => $client->post($this->endpoint, $this->body),
            Request::METHOD_PUT    => $client->put($this->endpoint, $this->body),
            Request::METHOD_DELETE => $client->delete($this->endpoint, $this->queryParams),
            default                => throw new \InvalidArgumentException("Unsupported HTTP method: {$this->method}"),
        };

        // Dispatch event with response data
        ClickUpApiCallCompleted::dispatch(
            endpoint: $this->endpoint,
            method: $this->method,
            response: $response->json() ?? [],
            statusCode: $response->status(),
            successful: $response->successful(),
        );
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('clickup-api-jobs')];
    }
}
