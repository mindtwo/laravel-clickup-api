<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

use Mindtwo\LaravelClickUpApi\Traits\HandlesTask;

class TaskTimeEstimateUpdated extends ClickUpEvent
{
    use HandlesTask;
}
