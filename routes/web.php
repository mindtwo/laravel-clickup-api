<?php

use Illuminate\Support\Facades\Route;
use Mindtwo\LaravelClickUpApi\Http\Controllers\WebhookController;
use Mindtwo\LaravelClickUpApi\Http\Middleware\VerifyClickUpWebhookSignature;

Route::middleware(config('clickup-api.webhook.middleware', ['api']))
    ->group(function () {
        Route::post(
            config('clickup-api.webhook.path', '/webhooks/clickup'),
            [WebhookController::class, 'handle']
        )->middleware(VerifyClickUpWebhookSignature::class)
            ->name('clickup.webhooks.handle');
    });
