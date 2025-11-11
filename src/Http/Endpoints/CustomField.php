<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Http\Endpoints;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelClickUpApi\ClickUpClient;

class CustomField
{
    public function __construct(protected ClickUpClient $api) {}

    /**
     * Get accessible custom fields for a list.
     *
     * @param int|string $listId The list ID
     *
     * @throws ConnectionException
     */
    public function show(int|string $listId): Response
    {
        return $this->api->client->get(sprintf('/list/%s/field', $listId));
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
    public function setValue(int|string $taskId, string $fieldId, array $data): Response
    {
        return $this->api->client->post(
            sprintf('/task/%s/field/%s', $taskId, $fieldId),
            $data
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
    public function removeValue(int|string $taskId, string $fieldId): Response
    {
        return $this->api->client->delete(
            sprintf('/task/%s/field/%s', $taskId, $fieldId)
        );
    }
}
