<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;
use Mindtwo\LaravelClickUpApi\Events\ClickUpApiCallCompleted;
use Mindtwo\LaravelClickUpApi\Events\TaskCreated;
use Mindtwo\LaravelClickUpApi\Events\TaskDeleted;
use Mindtwo\LaravelClickUpApi\Events\TaskUpdated;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;
use RuntimeException;
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

        $this->validateResponse($response);

        // Dispatch event with response data
        ClickUpApiCallCompleted::dispatch(
            $this->endpoint,
            $this->method,
            $response->json() ?? [],
            $response->status(),
            $response->successful(),
        );

        // Dispatch task-specific events
        $this->dispatchTaskEvents($response);
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

    /**
     * Dispatch task-specific events based on endpoint and method.
     */
    private function dispatchTaskEvents(Response $response): void
    {
        // Only dispatch task events for successful responses
        if (! $response->successful()) {
            return;
        }

        $responseData = $response->json() ?? [];

        // Task Created: POST /list/{listId}/task
        if ($this->method === 'POST' && preg_match('#^/list/([^/]+)/task$#', $this->endpoint, $matches)) {
            if (isset($responseData['id'])) {
                TaskCreated::dispatch(
                    $responseData,
                    EventSource::API,
                    true
                );
            }

            return;
        }

        // Task Updated: PUT /task/{taskId}
        if ($this->method === 'PUT' && preg_match('#^/task/([^/]+)$#', $this->endpoint, $matches)) {
            TaskUpdated::dispatch(
                $responseData,
                EventSource::API,
                true
            );

            return;
        }

        // Task Deleted: DELETE /task/{taskId}
        if ($this->method === 'DELETE' && preg_match('#^/task/([^/]+)$#', $this->endpoint, $matches)) {
            $taskId = $matches[1];

            TaskDeleted::dispatch(
                ['id' => $taskId],
                EventSource::API,
                true
            );

            return;
        }
    }

    /**
     * Validate HTTP response status and throw a runtime exception on failure.
     *
     * **Warning:** This method will execute lazy responses, no job retrieval will be possible after calling this.
     *
     * @throws RuntimeException
     */
    private function validateResponse(Response|LazyResponseProxy $response): void
    {
        if ($response->status() !== 200) {
            $error = $response->json()['err'] ?? 'Unknown error';
            throw new RuntimeException("ClickUp Api call failed: {$error}");
        }
    }
}
