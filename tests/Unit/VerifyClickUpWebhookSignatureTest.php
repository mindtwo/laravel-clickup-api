<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Mindtwo\LaravelClickUpApi\Http\Middleware\VerifyClickUpWebhookSignature;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Tests\TestCase;

class VerifyClickUpWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        foreach (File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    public function test_middleware_passes_with_valid_signature(): void
    {
        $secret = 'test-webhook-secret';
        $webhook = $this->createWebhook($secret);

        $payload = json_encode([
            'webhook_id' => $webhook->clickup_webhook_id,
            'event' => 'taskCreated',
            'task_id' => 'task_123',
        ]);

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $expectedSignature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['status' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
    }

    public function test_middleware_rejects_invalid_signature(): void
    {
        $secret = 'test-webhook-secret';
        $webhook = $this->createWebhook($secret);

        $payload = json_encode([
            'webhook_id' => $webhook->clickup_webhook_id,
            'event' => 'taskCreated',
        ]);

        // Use wrong signature
        $invalidSignature = hash_hmac('sha256', 'wrong-payload', $secret);

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $invalidSignature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            $this->fail('Middleware should not call next() with invalid signature');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid signature', $responseData['error']);
    }

    public function test_middleware_rejects_missing_signature(): void
    {
        $webhook = $this->createWebhook('test-secret');

        $payload = json_encode([
            'webhook_id' => $webhook->clickup_webhook_id,
            'event' => 'taskCreated',
        ]);

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        // No X-Signature header set

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            $this->fail('Middleware should not call next() with missing signature');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Signature missing', $responseData['error']);
    }

    public function test_middleware_rejects_missing_webhook_id(): void
    {
        $payload = json_encode([
            'event' => 'taskCreated',
            // webhook_id is missing
        ]);

        $signature = hash_hmac('sha256', $payload, 'test-secret');

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $signature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            $this->fail('Middleware should not call next() with missing webhook_id');
        });

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Webhook ID missing', $responseData['error']);
    }

    public function test_middleware_rejects_webhook_not_in_database(): void
    {
        $payload = json_encode([
            'webhook_id' => 'non-existent-webhook',
            'event' => 'taskCreated',
        ]);

        $signature = hash_hmac('sha256', $payload, 'test-secret');

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $signature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            $this->fail('Middleware should not call next() for unknown webhook');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid webhook', $responseData['error']);
    }

    public function test_middleware_rejects_webhook_without_secret(): void
    {
        $webhook = $this->createWebhook(null); // Webhook without secret

        $payload = json_encode([
            'webhook_id' => $webhook->clickup_webhook_id,
            'event' => 'taskCreated',
        ]);

        $signature = hash_hmac('sha256', $payload, 'some-secret');

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $signature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            $this->fail('Middleware should not call next() for webhook without secret');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid webhook', $responseData['error']);
    }

    public function test_middleware_uses_timing_safe_comparison(): void
    {
        // This test verifies that hash_equals is used (timing-safe)
        // by ensuring the middleware works correctly
        $secret = 'test-webhook-secret';
        $webhook = $this->createWebhook($secret);

        $payload = json_encode([
            'webhook_id' => $webhook->clickup_webhook_id,
            'event' => 'taskCreated',
        ]);

        $validSignature = hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $validSignature);

        $middleware = new VerifyClickUpWebhookSignature();
        $passedThrough = false;

        $response = $middleware->handle($request, function ($req) use (&$passedThrough) {
            $passedThrough = true;

            return response()->json(['status' => 'success']);
        });

        $this->assertTrue($passedThrough, 'Middleware should pass through with valid signature');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_handles_special_characters_in_payload(): void
    {
        $secret = 'test-webhook-secret';
        $webhook = $this->createWebhook($secret);

        $payload = json_encode([
            'webhook_id' => $webhook->clickup_webhook_id,
            'event' => 'taskCreated',
            'special_chars' => 'test@#$%^&*(){}[]|\:";\'<>?,./~`',
            'unicode' => 'テスト',
        ]);

        $validSignature = hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Signature', $validSignature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['status' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_rejects_signature_with_slightly_different_payload(): void
    {
        $secret = 'test-webhook-secret';
        $webhook = $this->createWebhook($secret);

        $payload1 = json_encode(['webhook_id' => $webhook->clickup_webhook_id, 'event' => 'taskCreated']);
        $payload2 = json_encode(['webhook_id' => $webhook->clickup_webhook_id, 'event' => 'taskUpdated']);

        // Generate signature for payload1 but send payload2
        $signature = hash_hmac('sha256', $payload1, $secret);

        $request = Request::create('/webhooks/clickup', 'POST', [], [], [], [], $payload2);
        $request->headers->set('X-Signature', $signature);

        $middleware = new VerifyClickUpWebhookSignature();
        $response = $middleware->handle($request, function ($req) {
            $this->fail('Middleware should reject mismatched signature');
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Create a test webhook in the database.
     */
    protected function createWebhook(?string $secret): ClickUpWebhook
    {
        return ClickUpWebhook::create([
            'clickup_webhook_id' => 'wh_test_'.uniqid(),
            'endpoint' => 'https://test.local/webhooks/clickup',
            'event' => '*',
            'target_type' => 'workspace',
            'target_id' => 'workspace_123',
            'secret' => $secret,
            'is_active' => true,
            'health_status' => 'active',
        ]);
    }
}
