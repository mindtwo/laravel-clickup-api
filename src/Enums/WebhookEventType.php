<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Enums;

enum WebhookEventType: string
{
    // Wildcard
    case ALL = '*';

    // Task events
    case TASK_CREATED = 'taskCreated';
    case TASK_UPDATED = 'taskUpdated';
    case TASK_DELETED = 'taskDeleted';
    case TASK_PRIORITY_UPDATED = 'taskPriorityUpdated';
    case TASK_STATUS_UPDATED = 'taskStatusUpdated';
    case TASK_ASSIGNEE_UPDATED = 'taskAssigneeUpdated';
    case TASK_DUE_DATE_UPDATED = 'taskDueDateUpdated';
    case TASK_TAG_UPDATED = 'taskTagUpdated';
    case TASK_MOVED = 'taskMoved';
    case TASK_COMMENT_POSTED = 'taskCommentPosted';
    case TASK_COMMENT_UPDATED = 'taskCommentUpdated';
    case TASK_TIME_ESTIMATE_UPDATED = 'taskTimeEstimateUpdated';
    case TASK_TIME_TRACKED_UPDATED = 'taskTimeTrackedUpdated';

    // List events
    case LIST_CREATED = 'listCreated';
    case LIST_UPDATED = 'listUpdated';
    case LIST_DELETED = 'listDeleted';

    // Folder events
    case FOLDER_CREATED = 'folderCreated';
    case FOLDER_UPDATED = 'folderUpdated';
    case FOLDER_DELETED = 'folderDeleted';

    // Space events
    case SPACE_CREATED = 'spaceCreated';
    case SPACE_UPDATED = 'spaceUpdated';
    case SPACE_DELETED = 'spaceDeleted';

    // Goal events
    case GOAL_CREATED = 'goalCreated';
    case GOAL_UPDATED = 'goalUpdated';
    case GOAL_DELETED = 'goalDeleted';
    case KEY_RESULT_CREATED = 'keyResultCreated';
    case KEY_RESULT_UPDATED = 'keyResultUpdated';
    case KEY_RESULT_DELETED = 'keyResultDeleted';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'All Events',
            self::TASK_CREATED => 'Task Created',
            self::TASK_UPDATED => 'Task Updated',
            self::TASK_DELETED => 'Task Deleted',
            self::TASK_PRIORITY_UPDATED => 'Task Priority Updated',
            self::TASK_STATUS_UPDATED => 'Task Status Updated',
            self::TASK_ASSIGNEE_UPDATED => 'Task Assignee Updated',
            self::TASK_DUE_DATE_UPDATED => 'Task Due Date Updated',
            self::TASK_TAG_UPDATED => 'Task Tag Updated',
            self::TASK_MOVED => 'Task Moved',
            self::TASK_COMMENT_POSTED => 'Task Comment Posted',
            self::TASK_COMMENT_UPDATED => 'Task Comment Updated',
            self::TASK_TIME_ESTIMATE_UPDATED => 'Task Time Estimate Updated',
            self::TASK_TIME_TRACKED_UPDATED => 'Task Time Tracked Updated',
            self::LIST_CREATED => 'List Created',
            self::LIST_UPDATED => 'List Updated',
            self::LIST_DELETED => 'List Deleted',
            self::FOLDER_CREATED => 'Folder Created',
            self::FOLDER_UPDATED => 'Folder Updated',
            self::FOLDER_DELETED => 'Folder Deleted',
            self::SPACE_CREATED => 'Space Created',
            self::SPACE_UPDATED => 'Space Updated',
            self::SPACE_DELETED => 'Space Deleted',
            self::GOAL_CREATED => 'Goal Created',
            self::GOAL_UPDATED => 'Goal Updated',
            self::GOAL_DELETED => 'Goal Deleted',
            self::KEY_RESULT_CREATED => 'Key Result Created',
            self::KEY_RESULT_UPDATED => 'Key Result Updated',
            self::KEY_RESULT_DELETED => 'Key Result Deleted',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ALL => 'Subscribe to all ClickUp events',
            self::TASK_CREATED => 'Triggered when a new task is created',
            self::TASK_UPDATED => 'Triggered when a task is updated',
            self::TASK_DELETED => 'Triggered when a task is deleted',
            self::TASK_PRIORITY_UPDATED => 'Triggered when a task priority is changed',
            self::TASK_STATUS_UPDATED => 'Triggered when a task status is changed',
            self::TASK_ASSIGNEE_UPDATED => 'Triggered when a task assignee is changed',
            self::TASK_DUE_DATE_UPDATED => 'Triggered when a task due date is changed',
            self::TASK_TAG_UPDATED => 'Triggered when a task tag is added or removed',
            self::TASK_MOVED => 'Triggered when a task is moved to another list',
            self::TASK_COMMENT_POSTED => 'Triggered when a comment is posted on a task',
            self::TASK_COMMENT_UPDATED => 'Triggered when a task comment is updated',
            self::TASK_TIME_ESTIMATE_UPDATED => 'Triggered when a task time estimate is changed',
            self::TASK_TIME_TRACKED_UPDATED => 'Triggered when time is tracked on a task',
            self::LIST_CREATED => 'Triggered when a new list is created',
            self::LIST_UPDATED => 'Triggered when a list is updated',
            self::LIST_DELETED => 'Triggered when a list is deleted',
            self::FOLDER_CREATED => 'Triggered when a new folder is created',
            self::FOLDER_UPDATED => 'Triggered when a folder is updated',
            self::FOLDER_DELETED => 'Triggered when a folder is deleted',
            self::SPACE_CREATED => 'Triggered when a new space is created',
            self::SPACE_UPDATED => 'Triggered when a space is updated',
            self::SPACE_DELETED => 'Triggered when a space is deleted',
            self::GOAL_CREATED => 'Triggered when a new goal is created',
            self::GOAL_UPDATED => 'Triggered when a goal is updated',
            self::GOAL_DELETED => 'Triggered when a goal is deleted',
            self::KEY_RESULT_CREATED => 'Triggered when a new key result is created',
            self::KEY_RESULT_UPDATED => 'Triggered when a key result is updated',
            self::KEY_RESULT_DELETED => 'Triggered when a key result is deleted',
        };
    }

    public function isTaskEvent(): bool
    {
        return str_starts_with($this->value, 'task');
    }

    public function isListEvent(): bool
    {
        return str_starts_with($this->value, 'list');
    }

    public function isFolderEvent(): bool
    {
        return str_starts_with($this->value, 'folder');
    }

    public function isSpaceEvent(): bool
    {
        return str_starts_with($this->value, 'space');
    }

    public function isGoalEvent(): bool
    {
        return str_starts_with($this->value, 'goal') || str_starts_with($this->value, 'keyResult');
    }
}
