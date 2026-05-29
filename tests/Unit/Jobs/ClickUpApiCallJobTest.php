<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelClickUpApi\Events\ClickUpApiCallCompleted;
use Mindtwo\LaravelClickUpApi\Events\Tasks\TaskUpdated;
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
