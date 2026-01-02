<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Events\Folders;

use Mindtwo\LaravelClickUpApi\Events\ClickUpEvent;

class FolderUpdated extends ClickUpEvent
{
    public function getFolderId(): string|int
    {
        return $this->payload['folder_id'];
    }
}
