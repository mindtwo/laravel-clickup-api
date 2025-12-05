<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Symfony\Component\HttpFoundation\Response;

class VerifyClickUpWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');

        if (! $signature) {
            Log::warning('ClickUp webhook signature missing', [
                'ip' => $request->ip(),
                'webhook_id' => $request->input('webhook_id'),
            ]);

            return response()->json(['error' => 'Signature missing'], 401);
        }

        // Get webhook ID from request
        $webhookId = $request->input('webhook_id');

        if (! $webhookId) {
            Log::warning('ClickUp webhook ID missing', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Webhook ID missing'], 400);
        }

        // Find webhook and its secret
        $webhook = ClickUpWebhook::where('clickup_webhook_id', $webhookId)->first();

        if (! $webhook || ! $webhook->secret) {
            Log::warning('ClickUp webhook secret not found', [
                'webhook_id' => $webhookId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid webhook'], 401);
        }

        // Verify signature
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhook->secret);

        if (! hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid ClickUp webhook signature', [
                'webhook_id' => $webhookId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
