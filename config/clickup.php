<?php

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
    'api_key' => env('CLICKUP_API_KEY', ''),

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
];
