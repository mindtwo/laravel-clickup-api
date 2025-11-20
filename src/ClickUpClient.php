<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Attachment;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\AuthorizedUser;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\CustomField;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Folder;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Milestone;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Space;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Subtask;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Tag;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Task;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskDependency;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskLink;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\TaskList;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Views;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\Workspaces;

class ClickUpClient
{
    public PendingRequest $client;

    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.clickup.com/api/v2'
    ) {
        $this->client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => $this->apiKey,
                'Accept'        => 'application/json',
            ]);
    }

    /**
     * Access the AuthorizedUser endpoint to get details about the authenticated user.
     */
    public function authorizedUser(): AuthorizedUser
    {
        return app(AuthorizedUser::class);
    }

    /**
     * Access the Task endpoint for managing ClickUp tasks.
     */
    public function tasks(): Task
    {
        return app(Task::class);
    }

    /**
     * Access the Space endpoint for managing ClickUp spaces/workspaces.
     */
    public function spaces(): Space
    {
        return app(Space::class);
    }

    /**
     * Access the Folder endpoint for managing ClickUp folders.
     */
    public function folders(): Folder
    {
        return app(Folder::class);
    }

    /**
     * Access the List endpoint for managing ClickUp lists.
     */
    public function lists(): TaskList
    {
        return app(TaskList::class);
    }

    /**
     * Access the CustomField endpoint for managing ClickUp custom fields.
     */
    public function customFields(): CustomField
    {
        return app(CustomField::class);
    }

    /**
     * Access the Attachment endpoint for uploading files to tasks.
     */
    public function attachments(): Attachment
    {
        return app(Attachment::class);
    }

    /**
     * Access the Subtask endpoint for managing subtasks.
     */
    public function subtasks(): Subtask
    {
        return app(Subtask::class);
    }

    /**
     * Access the Milestone endpoint for managing milestones.
     */
    public function milestones(): Milestone
    {
        return app(Milestone::class);
    }

    /**
     * Access the TaskDependency endpoint for managing task dependencies.
     */
    public function dependencies(): TaskDependency
    {
        return app(TaskDependency::class);
    }

    /**
     * Access the TaskLink endpoint for managing task links.
     */
    public function links(): TaskLink
    {
        return app(TaskLink::class);
    }

    /**
     * Access the Workspaces endpoint for managing workspaces.
     */
    public function workspaces(): Workspaces
    {
        return app(Workspaces::class);
    }

    /**
     * Access the Tag endpoint for managing task tags.
     */
    public function tags(): Tag
    {
        return app(Tag::class);
    }

    /**
     * Access the Views endpoint for managing views.
     */
    public function views(): Views
    {
        return app(Views::class);
    }
}
