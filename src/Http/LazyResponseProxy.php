<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;
use RuntimeException;

/**
 * Lazy response proxy that behaves like a Response but can also return a job.
 *
 * This class allows for flexible API call execution:
 * - By default, it auto-executes when Response methods are accessed
 * - Calling getJob() returns an undispatched job for manual queuing
 *
 * @mixin Response
 */
class LazyResponseProxy
{
    protected ?Response $response = null;

    protected bool $jobRetrieved = false;

    protected ClickUpApiCallJob $job;

    /**
     * @var true
     */
    private bool $executed = false;

    public function __construct(
        protected ClickUpClient $api,
        protected string $endpoint,
        protected string $method,
        protected array $body = [],
        protected array $queryParams = [],
        protected array $options = []
    ) {
        // Create job instance upfront but don't dispatch
        $this->job = new ClickUpApiCallJob(
            endpoint: $endpoint,
            method: $method,
            body: $body,
            queryParams: $queryParams,
            options: $options
        );
    }

    /**
     * Get the undispatched job for manual dispatching.
     *
     * Once this method is called, the API call will NOT be executed.
     * Attempting to access Response methods after calling this will throw an exception.
     */
    public function getJob(): ClickUpApiCallJob
    {
        if ($this->executed) {
            throw new RuntimeException('Cannot retrieve job after API call has been executed. ');
        }

        $this->jobRetrieved = true;

        return $this->job;
    }

    /**
     * Execute the API call and return the response.
     *
     * This method is called automatically when any Response method is accessed.
     * The response is cached so subsequent calls don't execute again.
     *
     * @throws RuntimeException If getJob() was already called
     * @throws ConnectionException
     */
    protected function execute(): Response
    {
        // Return cached response if already executed
        if ($this->response !== null) {
            return $this->response;
        }

        // Prevent execution if job was already retrieved
        if ($this->jobRetrieved) {
            throw new RuntimeException('Cannot execute API call after getJob() has been called. ');
        }

        if ($this->executed) {
            throw new RuntimeException('Cannot execute API call twice. Please Contact an Administrator, this error should never be thrown.');
        }

        // Get the HTTP client
        $client = $this->api->client;

        // Handle multipart requests
        if (isset($this->options['multipart']) && $this->options['multipart'] === true) {
            $client = $client->asMultipart();
        }

        // Handle query parameters for POST/PUT
        if (! empty($this->queryParams) && in_array($this->method, ['POST', 'PUT'], true)) {
            $client = $client->withQueryParameters($this->queryParams);
        }

        // Execute the request based on HTTP method
        $this->response = match ($this->method) {
            'GET'    => $client->get($this->endpoint, $this->queryParams),
            'POST'   => $client->post($this->endpoint, $this->body),
            'PUT'    => $client->put($this->endpoint, $this->body),
            'DELETE' => $client->delete($this->endpoint, $this->queryParams),
            default  => throw new \InvalidArgumentException("Unsupported HTTP method: {$this->method}"),
        };

        $this->executed = true;

        return $this->response;
    }

    /**
     * Proxy all method calls to the executed Response.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->execute()->$method(...$parameters);
    }

    /**
     * Support property access on the Response.
     */
    public function __get(string $name): mixed
    {
        return $this->execute()->$name;
    }

    /**
     * Support isset() checks on Response properties.
     */
    public function __isset(string $name): bool
    {
        return isset($this->execute()->$name);
    }

    /**
     * Support string casting.
     */
    public function __toString(): string
    {
        return (string) $this->execute();
    }
}
