<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Listeners;

use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;
use Mindtwo\LaravelClickUpApi\Events\ClickUpEvent;
use Throwable;

class LogClickUpEvent
{
    /**
     * Handle the event.
     */
    public function handle(ClickUpEvent $event): void
    {
        if (! config('clickup-api.logging.enabled', false)) {
            return;
        }

        try {
            $context = $this->buildLogContext($event);
            $message = $this->buildLogMessage($event);
            $level = config('clickup-api.logging.level', 'info');
            $channel = config('clickup-api.logging.channel');

            $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

            $logger->log($level, $message, $context);
        } catch (Throwable $e) {
            Log::error('Failed to log ClickUp event', [
                'event_class' => get_class($event),
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the log message.
     */
    protected function buildLogMessage(ClickUpEvent $event): string
    {
        $eventName = class_basename($event);
        $source = $this->formatSource($event->source);
        $status = $event->successful ? 'successful' : 'failed';

        return "ClickUp Event Fired: {$eventName} [{$source}] - {$status}";
    }

    /**
     * Build the context array for structured logging.
     *
     *
     * @return array<string, mixed>
     */
    protected function buildLogContext(ClickUpEvent $event): array
    {
        $context = [
            'event_class' => get_class($event),
            'event_name'  => class_basename($event),
            'source'      => $event->source->value,
            'source_type' => $event->source->isApi() ? 'API' : 'Webhook',
            'successful'  => $event->successful,
            'timestamp'   => now()->toIso8601String(),
        ];

        // Add entity identifiers if available (task_id, list_id, etc.)
        $context = array_merge($context, $this->extractEntityIds($event));

        // Optionally include full payload
        if (config('clickup-api.logging.include_payload', false)) {
            $context['payload'] = $event->payload;
        } else {
            // Include payload summary (count of items)
            $context['payload_keys'] = array_keys($event->payload);
            $context['payload_size'] = count($event->payload);
        }

        return $context;
    }

    /**
     * Extract entity identifiers from the event payload.
     *
     *
     * @return array<string, mixed>
     */
    protected function extractEntityIds(ClickUpEvent $event): array
    {
        $ids = [];

        // Extract task_id (webhook format)
        if (isset($event->payload['task_id'])) {
            $ids['task_id'] = $event->payload['task_id'];
        }

        // Extract id (API format)
        if (isset($event->payload['id']) && ! isset($ids['task_id'])) {
            $ids['entity_id'] = $event->payload['id'];
        }

        // Extract list_id
        if (isset($event->payload['list_id'])) {
            $ids['list_id'] = $event->payload['list_id'];
        } elseif (isset($event->payload['list']['id'])) {
            $ids['list_id'] = $event->payload['list']['id'];
        }

        // Extract space_id
        if (isset($event->payload['space_id'])) {
            $ids['space_id'] = $event->payload['space_id'];
        } elseif (isset($event->payload['space']['id'])) {
            $ids['space_id'] = $event->payload['space']['id'];
        }

        // Extract folder_id
        if (isset($event->payload['folder_id'])) {
            $ids['folder_id'] = $event->payload['folder_id'];
        } elseif (isset($event->payload['folder']['id'])) {
            $ids['folder_id'] = $event->payload['folder']['id'];
        }

        // Extract webhook_id (webhook events)
        if (isset($event->payload['webhook_id'])) {
            $ids['webhook_id'] = $event->payload['webhook_id'];
        }

        return $ids;
    }

    /**
     * Format the event source for display.
     */
    protected function formatSource(EventSource $source): string
    {
        return match ($source) {
            EventSource::API     => 'API',
            EventSource::WEBHOOK => 'Webhook',
        };
    }
}
