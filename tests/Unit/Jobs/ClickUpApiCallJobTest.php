<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelClickUpApi\Events\ClickUpApiCallCompleted;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskUpdated;
use Mindtwo\LaravelClickUpApi\Exceptions\ClickUpApiCallFailedException;
use Mindtwo\LaravelClickUpApi\Jobs\ClickUpApiCallJob;

uses()->group('clickup-api-call-job');

afterEach(function () {
    Mockery::close();
});

test('dispatches success completion event and task event on 200', function () {
    Event::fake([ClickUpApiCallCompleted::class, TaskUpdated::class]);
    Http::fake(['*' => Http::response(['id' => 'abc'], 200)]);

    (new ClickUpApiCallJob('/task/abc', 'PUT', ['name' => 'x']))->handle();

    Event::assertDispatched(
        ClickUpApiCallCompleted::class,
        fn (ClickUpApiCallCompleted $event) => $event->successful === true && $event->statusCode === 200,
    );
    Event::assertDispatched(TaskUpdated::class);
});

test('emits failure event and does not throw on terminal 404', function () {
    Event::fake([ClickUpApiCallCompleted::class]);
    Http::fake(['*' => Http::response(['err' => 'Not found'], 404)]);

    (new ClickUpApiCallJob('/task/gone', 'GET'))->handle();

    Event::assertDispatched(
        ClickUpApiCallCompleted::class,
        fn (ClickUpApiCallCompleted $event) => $event->successful === false && $event->statusCode === 404,
    );
});

test('emits failure event and does not throw on 429 rate limit', function () {
    Event::fake([ClickUpApiCallCompleted::class]);
    Http::fake(['*' => Http::response(['err' => 'Rate limit reached'], 429)]);

    (new ClickUpApiCallJob('/task/abc', 'GET'))->handle();

    Event::assertDispatched(
        ClickUpApiCallCompleted::class,
        fn (ClickUpApiCallCompleted $event) => $event->successful === false && $event->statusCode === 429,
    );
});

test('does not dispatch task events on a failed response', function () {
    Event::fake([ClickUpApiCallCompleted::class, TaskUpdated::class]);
    Http::fake(['*' => Http::response(['err' => 'Bad request'], 400)]);

    (new ClickUpApiCallJob('/task/abc', 'PUT', ['name' => 'x']))->handle();

    Event::assertNotDispatched(TaskUpdated::class);
});

test('failed handler emits a failure completion event for no-response failures', function () {
    Event::fake([ClickUpApiCallCompleted::class]);

    (new ClickUpApiCallJob('/task/abc', 'GET'))->failed(new RuntimeException('Connection timed out'));

    Event::assertDispatched(
        ClickUpApiCallCompleted::class,
        fn (ClickUpApiCallCompleted $event) => $event->successful === false && $event->statusCode === 0,
    );
});

test('backoff is exponential', function () {
    expect((new ClickUpApiCallJob('/task/abc', 'GET'))->backoff())->toBe([10, 30, 60, 120]);
});

test('failure exception exposes context and a descriptive message', function () {
    $exception = new ClickUpApiCallFailedException('/task/x', 'PUT', 400, ['err' => 'Bad request']);

    expect($exception->endpoint)->toBe('/task/x')
        ->and($exception->method)->toBe('PUT')
        ->and($exception->statusCode)->toBe(400)
        ->and($exception->response)->toBe(['err' => 'Bad request'])
        ->and($exception->getMessage())->toContain('400')
        ->and($exception->getMessage())->toContain('Bad request');
});

test('failure exception is built from a response', function () {
    Http::fake(['*' => Http::response(['err' => 'Not found'], 404)]);

    $response = Http::get('https://example.test/task/gone');

    $exception = ClickUpApiCallFailedException::fromResponse('/task/gone', 'GET', $response);

    expect($exception->endpoint)->toBe('/task/gone')
        ->and($exception->method)->toBe('GET')
        ->and($exception->statusCode)->toBe(404)
        ->and($exception->response)->toBe(['err' => 'Not found']);
});

test('failed handler does not re-dispatch for a terminal API failure exception', function () {
    Event::fake([ClickUpApiCallCompleted::class]);

    (new ClickUpApiCallJob('/task/x', 'GET'))->failed(
        new ClickUpApiCallFailedException('/task/x', 'GET', 404, ['err' => 'Not found']),
    );

    Event::assertNotDispatched(ClickUpApiCallCompleted::class);
});
