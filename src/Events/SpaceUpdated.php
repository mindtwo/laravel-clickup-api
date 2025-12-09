<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

class SpaceUpdated extends ClickUpEvent
{
    public function getSpaceId(): string|int
    {
        return $this->payload['space_id'];
    }
}
