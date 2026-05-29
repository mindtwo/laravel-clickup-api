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
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;
use Mindtwo\LaravelClickUpApi\Events\ClickUpApiCallCompleted;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskCreated;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskDeleted;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskUpdated;
use Mindtwo\LaravelClickUpApi\Exceptions\ClickUpApiCallFailedException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

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
     * The maximum number of seconds the job may run before timing out.
     */
    public int $timeout = 30;

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $options
     */
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

        // Always notify listeners of the outcome (success AND failure). Emitting on
        // failure is what lets consumers react to e.g. a 404 (deleted task) - the old
        // code threw before dispatching, so failure events never fired.
        ClickUpApiCallCompleted::dispatch(
            $this->endpoint,
            $this->method,
            $response->json() ?? [],
            $response->status(),
            $response->successful(),
        );

        if ($response->successful()) {
            $this->dispatchTaskEvents($response);

            return;
        }

        $status = $response->status();

        // Retry only transient failures (rate limiting / server errors), honoring
        // ClickUp's Retry-After. Terminal 4xx (e.g. 404 deleted, 400 bad request)
        // must NOT be retried - that was a source of pointless retry storms.
        if (($status === 429 || $status >= 500) && $this->attempts() < $this->tries) {
            $this->release($this->retryAfterSeconds($response));

            return;
        }

        Log::warning('ClickUp API call failed (terminal)', [
            'endpoint' => $this->endpoint,
            'method'   => $this->method,
            'status'   => $status,
            'error'    => $response->json()['err'] ?? 'Unknown error',
        ]);

        // If we reach this point the failure is terminal: fail the job with a specialized
        // exception so the failed-jobs store records the endpoint, method, status and error.
        $this->fail(ClickUpApiCallFailedException::fromResponse($this->endpoint, $this->method, $response));
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
     * Exponential backoff (seconds) between retry attempts.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    /**
     * Handle a job that has ultimately failed (e.g. connection timeout with no
     * HTTP response). The response-based path already emits a failure event for
     * any received non-200; this covers the no-response case.
     */
    public function failed(?Throwable $e): void
    {
        // Terminal HTTP failures already emitted an accurate completion event in handle();
        // re-dispatching here would duplicate it with a bogus statusCode 0.
        if ($e instanceof ClickUpApiCallFailedException) {
            return;
        }

        ClickUpApiCallCompleted::dispatch(
            $this->endpoint,
            $this->method,
            ['err' => $e?->getMessage() ?? 'ClickUp API job failed'],
            0,
            false,
        );
    }

    /**
     * Resolve how long to wait before retrying, honoring ClickUp rate-limit headers.
     */
    private function retryAfterSeconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return max(1, (int) $retryAfter);
        }

        // ClickUp exposes the reset moment as a UNIX timestamp.
        $reset = $response->header('X-RateLimit-Reset');

        if (is_numeric($reset)) {
            $delta = (int) $reset - time();

            if ($delta > 0) {
                return $delta;
            }
        }

        return 30;
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
}
