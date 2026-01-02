<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Mindtwo\LaravelClickUpApi\ClickUpClient;
use Mindtwo\LaravelClickUpApi\Http\LazyResponseProxy;

class CustomField
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Create a scope for workspace/team-level custom fields.
     *
     * @param int|string $teamId The workspace/team ID
     */
    public function forWorkspace(int|string $teamId): CustomFieldsScope
    {
        return new CustomFieldsScope($this->api, 'team', $teamId);
    }

    /**
     * Create a scope for space-level custom fields.
     *
     * @param int|string $spaceId The space ID
     */
    public function forSpace(int|string $spaceId): CustomFieldsScope
    {
        return new CustomFieldsScope($this->api, 'space', $spaceId);
    }

    /**
     * Create a scope for folder-level custom fields.
     *
     * @param int|string $folderId The folder ID
     */
    public function forFolder(int|string $folderId): CustomFieldsScope
    {
        return new CustomFieldsScope($this->api, 'folder', $folderId);
    }

    /**
     * Create a scope for list-level custom fields.
     *
     * @param int|string $listId The list ID
     */
    public function forList(int|string $listId): CustomFieldsScope
    {
        return new CustomFieldsScope($this->api, 'list', $listId);
    }

    /**
     * Get accessible custom fields for a list.
     *
     * @param int|string $listId The list ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $listId): LazyResponseProxy
    {
        $endpoint = sprintf('/list/%s/field', $listId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'GET'
        );
    }

    /**
     * Set or update a custom field value on a task.
     *
     * The data structure varies by field type:
     * - Text fields (url, email, phone, short_text, text): {"value": "string"}
     * - Checkbox: {"value": true/false}
     * - Number/Currency: {"value": 123}
     * - Date: {"value": 1234567890, "time": true} (Unix milliseconds)
     * - Dropdown: {"value": "option_id"}
     * - Labels: {"value": ["label_id_1", "label_id_2"]}
     * - Tasks/Users: {"value": {"add": [...], "rem": [...]}}
     * - Location: {"value": {"lat": 0.0, "lng": 0.0, "formatted_address": "..."}}
     * - Emoji (rating): {"value": 3} (0 to max count)
     * - Manual Progress: {"value": {"current": 50}}
     *
     * @param int|string $taskId The task ID
     * @param string $fieldId The custom field UUID
     * @param array<string, int|string> $data The field value data
     *
     * @throws ConnectionException
     */
    public function setValue(int|string $taskId, string $fieldId, array $data): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s/field/%s', $taskId, $fieldId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'POST',
            body: $data
        );
    }

    /**
     * Remove a custom field value from a task.
     *
     * This clears the field value for the specific task.
     * Note: This does NOT delete the field definition, only the value on this task.
     *
     * @param int|string $taskId The task ID
     * @param string $fieldId The custom field UUID
     *
     * @throws ConnectionException
     */
    public function removeValue(int|string $taskId, string $fieldId): LazyResponseProxy
    {
        $endpoint = sprintf('/task/%s/field/%s', $taskId, $fieldId);

        return new LazyResponseProxy(
            api: $this->api,
            endpoint: $endpoint,
            method: 'DELETE'
        );
    }
}
