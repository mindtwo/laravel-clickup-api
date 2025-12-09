<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Mindtwo\LaravelClickUpApi\Traits\HandlesTask;

class TaskAssigneeUpdated extends ClickUpEvent
{
    use HandlesTask;
}
