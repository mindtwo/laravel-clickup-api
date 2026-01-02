<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events\Spaces;

use Mindtwo\LaravelClickUpApi\Events\ClickUpEvent;

class SpaceCreated extends ClickUpEvent
{
    public function getSpaceId(): string|int
    {
        return $this->payload['space_id'];
    }
}
