<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events;

class FolderCreated extends ClickUpEvent
{
    public function getFolderId(): int
    {
        return $this->payload['folder_id'];
    }
}
