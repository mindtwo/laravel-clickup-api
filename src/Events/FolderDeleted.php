<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

class FolderDeleted extends ClickUpEvent
{
    public function getFolderId(): string|int
    {
        return $this->payload['folder_id'];
    }
}
