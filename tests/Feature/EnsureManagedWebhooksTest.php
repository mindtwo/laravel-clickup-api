<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Enums\WebhookHealthStatus;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Webhooks;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;

uses()->group('ensure-managed-webhooks');

beforeEach(function () {
    foreach (File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }
});

afterEach(function () {
    Mockery::close();
});

test('creates a missing required webhook', function () {
    $webhooks = ensureManagedWebhooksMock();

    $webhooks->shouldReceive('create')
        ->once()
        ->andReturn(ensureManagedResponse(200, [
            'webhook' => ['id' => 'wh_new', 'endpoint' => 'https://app.test/webhooks/clickup'],
        ]));

    $result = $webhooks->ensureManaged('ws_1', [
        ['events' => ['taskDeleted'], 'space_id' => 's1', 'endpoint' => 'https://app.test/webhooks/clickup'],
    ]);

    expect($result)->toHaveCount(1);

    $row = ClickUpWebhook::where('clickup_webhook_id', 'wh_new')->first();

    expect($row)->not->toBeNull()
        ->and($row->event)->toBe('taskDeleted')
        ->and($row->target_type)->toBe('space')
        ->and($row->target_id)->toBe('s1')
        ->and($row->is_active)->toBeTrue();
});

test('leaves an already active matching webhook untouched', function () {
    $webhooks = ensureManagedWebhooksMock();

    $webhooks->shouldReceive('create')->never();
    $webhooks->shouldReceive('update')->never();

    ensureManagedWebhook('wh_active', 'taskDeleted', 's1', active: true);

    $result = $webhooks->ensureManaged('ws_1', [
        ['events' => ['taskDeleted'], 'space_id' => 's1'],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result->first()->clickup_webhook_id)->toBe('wh_active')
        ->and(ClickUpWebhook::count())->toBe(1);
});

test('treats a wildcard webhook as covering the event', function () {
    $webhooks = ensureManagedWebhooksMock();

    $webhooks->shouldReceive('create')->never();
    $webhooks->shouldReceive('update')->never();

    ensureManagedWebhook('wh_wild', '*', 's1', active: true);

    $result = $webhooks->ensureManaged('ws_1', [
        ['events' => ['taskDeleted'], 'space_id' => 's1'],
    ]);

    expect($result->first()->clickup_webhook_id)->toBe('wh_wild');
});

test('reactivates an inactive matching webhook', function () {
    $webhooks = ensureManagedWebhooksMock();

    $existing = ensureManagedWebhook(
        'wh_1',
        'taskDeleted',
        's1',
        active: false,
        status: WebhookHealthStatus::FAILING,
        failCount: 60,
    );

    $webhooks->shouldReceive('update')
        ->once()
        ->with('wh_1', Mockery::on(fn ($data) => $data['status'] === 'active' && $data['events'] === ['taskDeleted']))
        ->andReturn(ensureManagedResponse(200));
    $webhooks->shouldReceive('create')->never();

    $webhooks->ensureManaged('ws_1', [
        ['events' => ['taskDeleted'], 'space_id' => 's1', 'endpoint' => 'https://app.test/webhooks/clickup'],
    ]);

    $existing->refresh();

    expect($existing->is_active)->toBeTrue()
        ->and($existing->health_status)->toBe(WebhookHealthStatus::ACTIVE)
        ->and($existing->fail_count)->toBe(0);
});

test('recreates a webhook that no longer exists in clickup', function () {
    $webhooks = ensureManagedWebhooksMock();

    ensureManagedWebhook('wh_gone', 'taskDeleted', 's1', active: false, status: WebhookHealthStatus::SUSPENDED);

    $webhooks->shouldReceive('update')
        ->once()
        ->with('wh_gone', Mockery::any())
        ->andReturn(ensureManagedResponse(404, ['err' => 'Webhook not found']));

    $webhooks->shouldReceive('create')
        ->once()
        ->andReturn(ensureManagedResponse(200, [
            'webhook' => ['id' => 'wh_new', 'endpoint' => 'https://app.test/webhooks/clickup'],
        ]));

    $result = $webhooks->ensureManaged('ws_1', [
        ['events' => ['taskDeleted'], 'space_id' => 's1', 'endpoint' => 'https://app.test/webhooks/clickup'],
    ]);

    expect($result->first()->clickup_webhook_id)->toBe('wh_new')
        ->and(ClickUpWebhook::where('clickup_webhook_id', 'wh_gone')->exists())->toBeFalse()
        ->and(ClickUpWebhook::where('clickup_webhook_id', 'wh_gone')->withTrashed()->exists())->toBeTrue()
        ->and(ClickUpWebhook::where('clickup_webhook_id', 'wh_new')->exists())->toBeTrue();
});

test('skips specs without events', function () {
    $webhooks = ensureManagedWebhooksMock();

    $webhooks->shouldReceive('create')->never();
    $webhooks->shouldReceive('update')->never();

    $result = $webhooks->ensureManaged('ws_1', [['events' => []]]);

    expect($result)->toHaveCount(0)
        ->and(ClickUpWebhook::count())->toBe(0);
});

/**
 * Build a partial mock of the Webhooks endpoint with a stubbed ClickUp client,
 * so the raw create()/update() HTTP calls can be mocked while the managed
 * reconcile logic runs for real.
 *
 * @return Webhooks&Mockery\MockInterface
 */
function ensureManagedWebhooksMock(): Webhooks
{
    $api = Mockery::mock(ClickUpClient::class);

    return Mockery::mock(Webhooks::class, [$api])->makePartial();
}

/**
 * Build a mocked LazyResponseProxy with the given status and JSON body.
 */
function ensureManagedResponse(int $status, array $json = []): LazyResponseProxy
{
    $response = Mockery::mock(LazyResponseProxy::class);
    $response->shouldReceive('status')->andReturn($status);
    $response->shouldReceive('json')->andReturn($json);

    return $response;
}

/**
 * Create a space-scoped webhook row for ensureManaged tests.
 */
function ensureManagedWebhook(
    string $clickUpWebhookId,
    string $event,
    string $spaceId,
    bool $active,
    WebhookHealthStatus $status = WebhookHealthStatus::ACTIVE,
    int $failCount = 0,
): ClickUpWebhook {
    return ClickUpWebhook::create([
        'clickup_webhook_id' => $clickUpWebhookId,
        'endpoint'           => 'https://app.test/webhooks/clickup',
        'event'              => $event,
        'target_type'        => 'space',
        'target_id'          => $spaceId,
        'secret'             => 'test-secret',
        'is_active'          => $active,
        'health_status'      => $status,
        'fail_count'         => $failCount,
    ]);
}
