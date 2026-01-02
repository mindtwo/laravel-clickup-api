<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events\Lists;

use Mindtwo\LaravelClickUpApi\Events\ClickUpEvent;

class ListCreated extends ClickUpEvent
{
    public function getListId(): string|int
    {
        return $this->payload['list_id'];
    }
}
