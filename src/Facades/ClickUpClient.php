<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Facades;

use Illuminate\Support\Facades\Facade;
use Mindtwo\LaravelClickUpApi\ClickUpClient as BaseClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Attachment;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\AuthorizedUser;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\CustomField;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Folder;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Milestone;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Space;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Subtask;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Task;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskDependency;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskLink;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskList;

/**
 * @method static Task tasks()
 * @method static Space spaces()
 * @method static Folder folders()
 * @method static TaskList lists()
 * @method static CustomField customFields()
 * @method static Attachment attachments()
 * @method static AuthorizedUser authorizedUser()
 * @method static Subtask subtasks()
 * @method static Milestone milestones()
 * @method static TaskDependency dependencies()
 * @method static TaskLink links()
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
