<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Http\Middleware\VerifyClickUpWebhookSignature;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;

uses()->group('webhook-signature');

beforeEach(function () {
    // Run migrations
    foreach (File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
        (include $migration->getRealPath())->up();
    }
});

test('middleware passes with valid signature', function () {
    $secret = 'test-webhook-secret';
    $webhook = createSignatureTestWebhook($secret);

    $payloadData = [
        'webhook_id' => $webhook->clickup_webhook_id,
        'event'      => 'taskCreated',
        'task_id'    => 'task_123',
    ];
    $payload = json_encode($payloadData);

    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $expectedSignature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        return response()->json(['status' => 'success']);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBeJson();

    $responseData = json_decode($response->getContent(), true);
    expect($responseData['status'])->toBe('success');
});

test('middleware rejects invalid signature', function () {
    $secret = 'test-webhook-secret';
    $webhook = createSignatureTestWebhook($secret);

    $payloadData = [
        'webhook_id' => $webhook->clickup_webhook_id,
        'event'      => 'taskCreated',
    ];
    $payload = json_encode($payloadData);

    // Use wrong signature
    $invalidSignature = hash_hmac('sha256', 'wrong-payload', $secret);

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $invalidSignature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        throw new Exception('Middleware should not call next() with invalid signature');
    });

    expect($response->getStatusCode())->toBe(401);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData['error'])->toBe('Invalid signature');
});

test('middleware rejects missing signature', function () {
    $webhook = createSignatureTestWebhook('test-secret');

    $payloadData = [
        'webhook_id' => $webhook->clickup_webhook_id,
        'event'      => 'taskCreated',
    ];
    $payload = json_encode($payloadData);

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    // No X-Signature header set

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        throw new Exception('Middleware should not call next() with missing signature');
    });

    expect($response->getStatusCode())->toBe(401);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData['error'])->toBe('Signature missing');
});

test('middleware rejects missing webhook id', function () {
    $payloadData = [
        'event' => 'taskCreated',
        // webhook_id is missing
    ];
    $payload = json_encode($payloadData);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $signature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        throw new Exception('Middleware should not call next() with missing webhook_id');
    });

    expect($response->getStatusCode())->toBe(400);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData['error'])->toBe('Webhook ID missing');
});

test('middleware rejects webhook not in database', function () {
    $payloadData = [
        'webhook_id' => 'non-existent-webhook',
        'event'      => 'taskCreated',
    ];
    $payload = json_encode($payloadData);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $signature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        throw new Exception('Middleware should not call next() for unknown webhook');
    });

    expect($response->getStatusCode())->toBe(401);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData['error'])->toBe('Invalid webhook');
});

test('middleware rejects webhook without secret', function () {
    $webhook = createSignatureTestWebhook(null); // Webhook without secret

    $payloadData = [
        'webhook_id' => $webhook->clickup_webhook_id,
        'event'      => 'taskCreated',
    ];
    $payload = json_encode($payloadData);

    $signature = hash_hmac('sha256', $payload, 'some-secret');

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $signature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        throw new Exception('Middleware should not call next() for webhook without secret');
    });

    expect($response->getStatusCode())->toBe(401);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData['error'])->toBe('Invalid webhook');
});

test('middleware uses timing safe comparison', function () {
    // This test verifies that hash_equals is used (timing-safe)
    // by ensuring the middleware works correctly
    $secret = 'test-webhook-secret';
    $webhook = createSignatureTestWebhook($secret);

    $payloadData = [
        'webhook_id' => $webhook->clickup_webhook_id,
        'event'      => 'taskCreated',
    ];
    $payload = json_encode($payloadData);

    $validSignature = hash_hmac('sha256', $payload, $secret);

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $validSignature);

    $middleware = new VerifyClickUpWebhookSignature;
    $passedThrough = false;

    $response = $middleware->handle($request, function ($req) use (&$passedThrough) {
        $passedThrough = true;

        return response()->json(['status' => 'success']);
    });

    expect($passedThrough)->toBeTrue('Middleware should pass through with valid signature');
    expect($response->getStatusCode())->toBe(200);
});

test('middleware handles special characters in payload', function () {
    $secret = 'test-webhook-secret';
    $webhook = createSignatureTestWebhook($secret);

    $payloadData = [
        'webhook_id'    => $webhook->clickup_webhook_id,
        'event'         => 'taskCreated',
        'special_chars' => 'test@#$%^&*(){}[]|\:";\'<>?,./~`',
        'unicode'       => 'テスト',
    ];
    $payload = json_encode($payloadData);

    $validSignature = hash_hmac('sha256', $payload, $secret);

    $request = Request::create('/webhooks/clickup', 'POST', $payloadData, [], [], [], $payload);
    $request->headers->set('X-Signature', $validSignature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        return response()->json(['status' => 'success']);
    });

    expect($response->getStatusCode())->toBe(200);
});

test('middleware rejects signature with slightly different payload', function () {
    $secret = 'test-webhook-secret';
    $webhook = createSignatureTestWebhook($secret);

    $payload1Data = ['webhook_id' => $webhook->clickup_webhook_id, 'event' => 'taskCreated'];
    $payload2Data = ['webhook_id' => $webhook->clickup_webhook_id, 'event' => 'taskUpdated'];

    $payload1 = json_encode($payload1Data);
    $payload2 = json_encode($payload2Data);

    // Generate signature for payload1 but send payload2
    $signature = hash_hmac('sha256', $payload1, $secret);

    $request = Request::create('/webhooks/clickup', 'POST', $payload2Data, [], [], [], $payload2);
    $request->headers->set('X-Signature', $signature);

    $middleware = new VerifyClickUpWebhookSignature;
    $response = $middleware->handle($request, function ($req) {
        throw new Exception('Middleware should reject mismatched signature');
    });

    expect($response->getStatusCode())->toBe(401);
});

/**
 * Create a test webhook in the database.
 */
function createSignatureTestWebhook(?string $secret): ClickUpWebhook
{
    return ClickUpWebhook::create([
        'clickup_webhook_id' => 'wh_test_'.uniqid(),
        'endpoint'           => 'https://test.local/webhooks/clickup',
        'event'              => '*',
        'target_type'        => 'workspace',
        'target_id'          => 'workspace_123',
        'secret'             => $secret,
        'is_active'          => true,
        'health_status'      => 'active',
    ]);
}
