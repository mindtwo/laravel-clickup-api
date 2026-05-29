<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhookDelivery;

uses()->group('clickup-webhook-delivery');

beforeEach(function () {
    foreach (File::allFiles(__DIR__.'/../../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }
});

test('prunable targets deliveries older than the retention window', function () {
    config(['clickup-api.deliveries.retention_days' => 30]);

    $old = makeDelivery('old');
    $old->created_at = now()->subDays(31);
    $old->save();

    $recent = makeDelivery('recent');
    $recent->created_at = now()->subDays(5);
    $recent->save();

    $prunableIds = (new ClickUpWebhookDelivery)->prunable()->pluck('id');

    expect($prunableIds)->toContain($old->id)
        ->and($prunableIds)->not->toContain($recent->id);
});

test('retention window is configurable', function () {
    config(['clickup-api.deliveries.retention_days' => 7]);

    $delivery = makeDelivery('mid');
    $delivery->created_at = now()->subDays(10);
    $delivery->save();

    expect((new ClickUpWebhookDelivery)->prunable()->pluck('id'))->toContain($delivery->id);
});

function makeDelivery(string $key): ClickUpWebhookDelivery
{
    return ClickUpWebhookDelivery::create([
        'clickup_webhook_id' => 1,
        'event'              => 'taskUpdated',
        'payload'            => ['event' => 'taskUpdated'],
        'status'             => 'processed',
        'idempotency_key'    => $key,
    ]);
}
