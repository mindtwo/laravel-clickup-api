<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;
use Mindtwo\LaravelClickUpApi\Events\FolderCreated;
use Mindtwo\LaravelClickUpApi\Events\FolderDeleted;
use Mindtwo\LaravelClickUpApi\Events\FolderUpdated;
use Mindtwo\LaravelClickUpApi\Events\GoalCreated;
use Mindtwo\LaravelClickUpApi\Events\GoalDeleted;
use Mindtwo\LaravelClickUpApi\Events\GoalUpdated;
use Mindtwo\LaravelClickUpApi\Events\KeyResultCreated;
use Mindtwo\LaravelClickUpApi\Events\KeyResultDeleted;
use Mindtwo\LaravelClickUpApi\Events\KeyResultUpdated;
use Mindtwo\LaravelClickUpApi\Events\ListCreated;
use Mindtwo\LaravelClickUpApi\Events\ListDeleted;
use Mindtwo\LaravelClickUpApi\Events\ListUpdated;
use Mindtwo\LaravelClickUpApi\Events\SpaceCreated;
use Mindtwo\LaravelClickUpApi\Events\SpaceDeleted;
use Mindtwo\LaravelClickUpApi\Events\SpaceUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskAssigneeUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskCommentPosted;
use Mindtwo\LaravelClickUpApi\Events\TaskCommentUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskCreated;
use Mindtwo\LaravelClickUpApi\Events\TaskDeleted;
use Mindtwo\LaravelClickUpApi\Events\TaskDueDateUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskMoved;
use Mindtwo\LaravelClickUpApi\Events\TaskPriorityUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskStatusUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskTagUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskTimeEstimateUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskTimeTrackedUpdated;
use Mindtwo\LaravelClickUpApi\Events\TaskUpdated;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhook;
use Mindtwo\LaravelClickUpApi\Models\ClickUpWebhookDelivery;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook from ClickUp.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $startTime = now();

            // Extract webhook data
            $webhookId = $request->input('webhook_id');
            $event = $request->input('event');
            $historyItems = $request->input('history_items', []);

            // Generate idempotency key using webhook_id and history_item_id
            $idempotencyKey = $webhookId.':'.($historyItems[0]['id'] ?? uniqid(more_entropy: true));

            // Check for duplicate delivery
            if (ClickUpWebhookDelivery::where('idempotency_key', $idempotencyKey)->exists()) {
                Log::info('Duplicate ClickUp webhook delivery detected', [
                    'webhook_id'      => $webhookId,
                    'event'           => $event,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return response()->json(['status' => 'duplicate'], 200);
            }

            // Find webhook configuration
            $webhook = ClickUpWebhook::where('clickup_webhook_id', $webhookId)
                ->where('is_active', true)
                ->first();

            if (! $webhook) {
                Log::warning('ClickUp webhook not found or inactive', [
                    'webhook_id' => $webhookId,
                    'event'      => $event,
                ]);

                return response()->json(['status' => 'webhook_not_found'], 404);
            }

            // Record delivery
            $delivery = $webhook->deliveries()->create([
                'event'           => $event,
                'payload'         => $request->all(),
                'status'          => 'received',
                'idempotency_key' => $idempotencyKey,
            ]);

            try {
                // Dispatch event
                $this->dispatchEvent($event, $request->all());

                // Update delivery status
                $processingTime = now()->diffInMilliseconds($startTime);
                $delivery->update([
                    'status'             => 'processed',
                    'processing_time_ms' => $processingTime,
                ]);

                $webhook->recordDelivery();

                return response()->json(['status' => 'success'], 200);
            } catch (Exception $e) {
                $delivery->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $webhook->recordFailure($e->getMessage());

                Log::error('ClickUp webhook processing failed', [
                    'webhook_id' => $webhookId,
                    'event'      => $event,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('ClickUp webhook handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch the appropriate Laravel event based on ClickUp event type.
     */
    private function dispatchEvent(string $eventType, array $payload): void
    {
        // Map ClickUp events to Laravel events
        $eventClass = match ($eventType) {
            // Task events
            'taskCreated'             => TaskCreated::class,
            'taskUpdated'             => TaskUpdated::class,
            'taskDeleted'             => TaskDeleted::class,
            'taskPriorityUpdated'     => TaskPriorityUpdated::class,
            'taskStatusUpdated'       => TaskStatusUpdated::class,
            'taskAssigneeUpdated'     => TaskAssigneeUpdated::class,
            'taskDueDateUpdated'      => TaskDueDateUpdated::class,
            'taskTagUpdated'          => TaskTagUpdated::class,
            'taskMoved'               => TaskMoved::class,
            'taskCommentPosted'       => TaskCommentPosted::class,
            'taskCommentUpdated'      => TaskCommentUpdated::class,
            'taskTimeEstimateUpdated' => TaskTimeEstimateUpdated::class,
            'taskTimeTrackedUpdated'  => TaskTimeTrackedUpdated::class,

            // List events
            'listCreated' => ListCreated::class,
            'listUpdated' => ListUpdated::class,
            'listDeleted' => ListDeleted::class,

            // Folder events
            'folderCreated' => FolderCreated::class,
            'folderUpdated' => FolderUpdated::class,
            'folderDeleted' => FolderDeleted::class,

            // Space events
            'spaceCreated' => SpaceCreated::class,
            'spaceUpdated' => SpaceUpdated::class,
            'spaceDeleted' => SpaceDeleted::class,

            // Goal events
            'goalCreated'      => GoalCreated::class,
            'goalUpdated'      => GoalUpdated::class,
            'goalDeleted'      => GoalDeleted::class,
            'keyResultCreated' => KeyResultCreated::class,
            'keyResultUpdated' => KeyResultUpdated::class,
            'keyResultDeleted' => KeyResultDeleted::class,

            default => null,
        };

        if ($eventClass) {
            event(new $eventClass($payload, EventSource::WEBHOOK));

            Log::debug('ClickUp webhook event dispatched', [
                'event_type'  => $eventType,
                'event_class' => $eventClass,
            ]);
        } else {
            Log::warning('Unknown ClickUp webhook event type', [
                'event_type' => $eventType,
            ]);
        }
    }
}
