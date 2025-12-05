<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mindtwo\LaravelClickUpApi\Enums\EventSource;

class ListUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $payload,
        public EventSource $source = EventSource::WEBHOOK,
    ) {}

    public function getListId(): string|int
    {
        return $this->payload['list_id'];
    }

    public function getHistoryItems(): array
    {
        return $this->payload['history_items'] ?? [];
    }

    public function isFromWebhook(): bool
    {
        return $this->source === 'webhook';
    }
}
