<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Mindtwo\LaravelClickUpApi\Enums\EventSource;
use Mindtwo\LaravelClickUpApi\Traits\HandlesTask;

class TaskDeleted extends ClickUpEvent
{
    use HandlesTask;
}
