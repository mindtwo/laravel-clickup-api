<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Mindtwo\LaravelClickUpApi\Traits\HandlesTask;

class TaskTimeTrackedUpdated extends ClickUpEvent
{
    use HandlesTask;
}
