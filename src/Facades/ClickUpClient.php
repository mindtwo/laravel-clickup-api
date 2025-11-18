<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Facades;

use Illuminate\Support\Facades\Facade;
use Mindtwo\LaravelClickUpApi\ClickUpClient as BaseClickUpClient;

/**
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\Task tasks()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\Space spaces()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\Folder folders()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskList lists()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\CustomField customFields()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\Attachment attachments()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\Subtask subtasks()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\Milestone milestones()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskDependency dependencies()
 * @method static \Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskLink links()
 *
 * @see BaseClickUpClient
 */
class ClickUpClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseClickUpClient::class;
    }
}
