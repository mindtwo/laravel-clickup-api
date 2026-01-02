<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ClickUp API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate requests to the ClickUp API. This key
    | provides your application with access to ClickUp's features and should
    | be kept secure. The key is read from your application's environment
    | file, allowing for different keys in your development and production
    | environments.
    |
    | Default: ''
    |
    */

    'api_key' => (string) env('MINDTWO_CLICKUP_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Workspace ID
    |--------------------------------------------------------------------------
    |
    | The default ClickUp Workspace ID to be used for API operations. This will determine
    | the context in which tasks and other entities are created or managed within
    |
    | Default: ''
    |
    */

    'default_workspace_id' => (string) env('MINDTWO_CLICKUP_WORKSPACE_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue API calls
    |--------------------------------------------------------------------------
    |
    | Determines whether API calls to ClickUp should be queued for asynchronous
    | processing. Enabling this option can help manage rate limits and improve
    | application performance by offloading API requests to a background queue.
    |
    | Default: false
    |
    */

    'queue'                 => (bool) env('MINDTWO_CLICKUP_QUEUE_API_CALLS', false),
    'queue_connection'      => env('MINDTWO_CLICKUP_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
    'rate_limit_per_minute' => (int) env('MINDTWO_CLICKUP_RATE_LIMIT_PER_MINUTE', 100),

    /*
    |--------------------------------------------------------------------------
    | ClickUp Mappings
    |--------------------------------------------------------------------------
    |
    | This section defines the mapping between ClickUp IDs and more
    | readable, user-friendly configuration keys for use within the application.
    | These mappings simplify the process of referring to specific entries or
    | features in ClickUp, enabling developers to use meaningful identifiers
    | instead of directly coding with the numeric IDs.
    |
    | Example mapping:
    | 'sales' => '123456789', // Maps to the ClickUp task for user registration features
    |
    | It's advisable to manage these mappings in a way that reflects the current
    | tasks and projects within your ClickUp workspace, ensuring that the
    | application remains aligned with project management activities.
    |
    | Default: []
    |
    */

    'mappings' => [
        // ...
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for ClickUp webhook handling. This enables your
    | application to receive and process webhook events from ClickUp.
    |
    */

    'webhook' => [
        // Enable or disable webhook functionality
        'enabled' => (bool) env('MINDTWO_CLICKUP_WEBHOOK_ENABLED', true),

        // The URL path where webhooks will be received
        'path' => env('MINDTWO_CLICKUP_WEBHOOK_PATH', '/webhooks/clickup'),

        // Middleware to apply to the webhook route
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Controls logging behavior for ClickUp events. When enabled, all events
    | extending ClickUpEvent will be automatically logged with their source,
    | event type, and relevant context data.
    |
    */

    'logging' => [
        // Enable or disable event logging
        'enabled' => (bool) env('MINDTWO_CLICKUP_LOG_EVENTS', false),

        // Log level to use (debug, info, notice, warning, error)
        'level' => env('MINDTWO_CLICKUP_LOG_LEVEL', 'info'),

        // Log channel to use (null = default channel)
        'channel' => env('MINDTWO_CLICKUP_LOG_CHANNEL', null),

        // Include full payload in logs (can be verbose)
        'include_payload' => (bool) env('MINDTWO_CLICKUP_LOG_INCLUDE_PAYLOAD', false),
    ],

];
