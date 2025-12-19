# Laravel ClickUp API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-clickup-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-clickup-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-clickup-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-clickup-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-clickup-api/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mindtwo/laravel-clickup-api/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-clickup-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-clickup-api)

This Laravel package provides a convenient and efficient way to integrate your
Laravel application with ClickUp, a popular project management tool. Designed
for ease of use, it offers a seamless connection, enabling your Laravel
application to interact
with [ClickUp's APIs](https://developer.clickup.com/docs/general-v2-v3-api) for
task management, project tracking, and more. Whether you're looking to automate
project updates, synchronize tasks, or enhance team collaboration, this package
streamlines the process, making it easier to keep your projects organized and
up-to-date directly from your Laravel application.

**Please note:** that this package currently supports a limited set of ClickUp
endpoints, specifically those related to Tasks, Attachments, and Custom Fields.
We are actively working to expand our coverage of ClickUp's API to include more
endpoints and functionalities.
If you require integration with aspects of ClickUp not yet covered by our
package, we appreciate your patience and welcome contributions or suggestions to
enhance our offering.

## Installation

You can install the package via composer:

```bash
composer require mindtwo/laravel-clickup-api
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="clickup-api-config"
```

This is the contents of the published [config file](config/clickup-api.php).

You must publish and run the package migrations:

```bash
php artisan vendor:publish --tag="clickup-api-migrations"
php artisan migrate
```

The migrations will create the necessary database tables for managing ClickUp webhooks, including:
- `clickup_webhooks` - Stores webhook registrations and health status
- `clickup_webhook_deliveries` - Tracks webhook delivery history

## ENV Configuration

To ensure the proper functioning of this Laravel package with ClickUp, you must
provide your ClickUp API key by setting the `CLICKUP_API_KEY` constant in your
application's environment configuration. Here's how to find your ClickUp API
key:

1. Log in to your ClickUp account and navigate to your profile settings by
   clicking on your profile picture in the bottom left corner.
2. Select "Apps" from the sidebar menu.
3. Scroll to the "API" section and find the "Generate" button to create a new
   API key. If you've already generated one, it will be displayed here.
4. Copy the API key and add it to your Laravel `.env` file as follows:
   ```
   CLICKUP_API_KEY=your_clickup_api_key_here
   ```

Make sure to replace `your_clickup_api_key_here` with the actual API key you
obtained from ClickUp. This step is crucial for authenticating your Laravel
application's requests to ClickUp's API.

## Usage

This package provides a clean and intuitive Facade interface for interacting with ClickUp's API. All endpoints can be accessed through the `ClickUp` facade, making your code more readable and maintainable.

### Using the Facade

First, import the ClickUp facade at the top of your PHP file:

```php
use Mindtwo\LaravelClickUpApi\Facades\ClickUpClient as ClickUp;
```

Now you can access all ClickUp endpoints through the facade:

```php
// Tasks
ClickUp::tasks()->create($listId, $taskDetails);
ClickUp::tasks()->index($listId, $filters);
ClickUp::tasks()->show($taskId);
ClickUp::tasks()->update($taskId, $updates);
ClickUp::tasks()->delete($taskId);

// Spaces
ClickUp::spaces()->index($teamId);
ClickUp::spaces()->create($teamId, $spaceDetails);

// Folders
ClickUp::folders()->index($spaceId);
ClickUp::folders()->create($spaceId, $folderDetails);

// Lists
ClickUp::lists()->index($folderId);
ClickUp::lists()->create($folderId, $listDetails);

// Custom Fields
ClickUp::customFields()->show($listId);
ClickUp::customFields()->set($taskId, $fieldId, $value);

// Attachments
ClickUp::attachments()->create($taskId, $fileData);

// Subtasks
ClickUp::subtasks()->create($taskId, $subtaskDetails);

// Milestones
ClickUp::milestones()->create($listId, $milestoneDetails);

// Task Dependencies
ClickUp::dependencies()->add($taskId, $dependsOn, $dependencyType);
ClickUp::dependencies()->remove($taskId, $dependsOn, $dependencyType);

// Task Links
ClickUp::links()->create($taskId, $linksTo);
ClickUp::links()->remove($taskId, $linksTo);
```

### Alternative: Using the app() Helper

If you prefer not to use facades, you can still access endpoints using the `app()` helper:

```php
app(\Mindtwo\LaravelClickUpApi\Http\Endpoints\Task::class)->create($listId, $taskDetails);
```

However, we recommend using the Facade for cleaner and more readable code.

### Task Endpoint Usage

The `Task` class within the Laravel ClickUp API package provides a simple
interface for interacting with ClickUp's Task-related API endpoints. This class
allows you to list, view, create, update, and delete tasks within ClickUp,
directly from your Laravel application. Here's a quick overview of how to use
it:

- **List Tasks**: Retrieve all tasks within a specific list by providing the
  list ID.
- **Show Task Details**: Get detailed information about a specific task using
  its task ID.
- **Create Task**: Create a new task within a list by providing the list ID and
  an array of task details.
- **Update Task**: Update an existing task by providing the task ID and the
  details to be updated.
- **Delete Task**: Delete a task using its task ID.

#### How to Use:

First, import the facade:

```php
use Mindtwo\LaravelClickUpApi\Facades\ClickUpClient as ClickUp;
```

1. **Create a Task**: To create a new task within a list, you can use the
   `create` method. Pass the list ID where the task should be created and an
   array of data that specifies the task details.

   ```php
   $taskDetails = [
       'name' => 'New Task', // Mandatory
       'description' => 'Task description', // Optional
       // Add other task details as needed
   ];

   $task = ClickUp::tasks()->create($listId, $taskDetails);
   ```

2. **Get Tasks in a List**: To retrieve tasks within a list, use the `index`
   method with the list ID and an optional array of query parameters to filter
   the tasks.

   ```php
   $tasks = ClickUp::tasks()->index($listId, []);
   ```

3. **Show Task Details**: To get detailed information about a task, use the
   `show` method with the task ID.

   ```php
   $task = ClickUp::tasks()->show($taskId);
   ```

4. **Update a Task**: To update an existing task, use the `update` method with
   the task ID and an array of data with the details you wish to update.

   ```php
   $updatedDetails = [
       'name' => 'Updated Task Name',
       // Other task details you want to update
   ];

   $updatedTask = ClickUp::tasks()->update($taskId, $updatedDetails);
   ```

5. **Delete a Task**: To delete a task, use the `delete` method with the task
   ID.

   ```php
   $response = ClickUp::tasks()->delete($taskId);
   ```

These examples demonstrate the fundamental operations you can perform on tasks
within ClickUp through your Laravel application, providing a powerful way to
integrate task management functionalities into your workflow.

### Attachment Endpoint Usage

The `Attachment` class within this Laravel package facilitates the creation of
attachments in tasks on ClickUp. By utilizing the `create` method, users can
easily upload files and associate them with a specific task by its ID. This
functionality enhances the task management process, allowing for a more detailed
and resource-rich task structure.

To use this endpoint, first ensure that you have a task ID (`$taskId`) where you
want to attach a file, and prepare the data (`$data`) according to the ClickUp
API specifications for attachments. The `$data` should include the file
information structured in a way that's compatible with ClickUp's expectations
for attachment uploads.

Here's a basic example on how to use the `Attachment` endpoint:

```php
<?php

use Mindtwo\LaravelClickUpApi\Facades\ClickUpClient as ClickUp;

// Assuming $taskId holds the ID of the task you want to attach a file to
// and $data contains the file and other required information as an array
$taskId = 'your_task_id_here';
$data = [
    [
        'name' => 'file',
        'contents' => fopen('/path/to/your/file', 'r'),
        'filename' => 'filename.ext',
    ],
    // Add other data fields as required by the ClickUp API for an attachment
];

// Creating an attachment to a task
ClickUp::attachments()->create($taskId, $data);
```

This simple interface abstracts away the complexity of dealing with multipart
file uploads and the ClickUp API, allowing you to focus on building your
application. Remember to replace `'your_task_id_here'` with the actual ID of the
task you're targeting and to adjust the `$data` array with the correct file path
and other necessary information as per your requirements.

### Custom Fields Endpoint Usage

The `CustomField` class within our Laravel ClickUp API package serves as a
dedicated endpoint for interacting with custom fields in ClickUp. Custom fields
are pivotal in tailoring ClickUp's lists to your project's specific
requirements, allowing for the addition of unique data fields to tasks.
Utilizing the `CustomField` class, you can effortlessly retrieve all custom
fields associated with a specific list by providing the list's ID.

Here's a quick guide on how to use the `CustomField` endpoint to fetch custom
fields:

```php
// Import the ClickUp facade at the top of your PHP file
use Mindtwo\LaravelClickUpApi\Facades\ClickUpClient as ClickUp;

// Assuming you have a list ID, you can retrieve its custom fields like so:
$listId = 'your_list_id_here'; // Replace with your actual list ID

// Fetch the custom fields for the specified list
$customFields = ClickUp::customFields()->show($listId);

// $customFields now contains the response from ClickUp API
```

Ensure you replace `'your_list_id_here'` with the actual ID of the list whose
custom fields you wish to retrieve. This simple and intuitive approach allows
you to integrate custom field data from ClickUp directly into your Laravel
application, enhancing data management and project customization capabilities.

### ListCustomFieldsCommand

The ListCustomFieldsCommand class is a Laravel console command provided by the
Laravel ClickUp API package, enabling users to list all available custom fields
for a specified list in ClickUp. By executing the command php artisan clickup:
list-custom-fields, users can either provide a list ID or mapping key directly
via the --list option or they will be prompted to enter it. This command is
particularly useful for developers and administrators who need to quickly view
the custom field configurations within their ClickUp lists, including details
such as field ID, name, type, configuration options, creation date, visibility
to guests, and whether the field is required. The command outputs this
information in a well-organized table format, making it easy to read and analyze
directly from the terminal.

## Webhook Security

Securing your ClickUp webhook endpoints is crucial to ensure that only legitimate webhook events from ClickUp are processed by your application. This package provides built-in signature verification using HMAC-SHA256.

### How Webhook Signature Verification Works

When ClickUp sends a webhook event to your application, it includes an `X-Signature` header containing an HMAC-SHA256 hash of the webhook payload. This signature is generated using a secret key that is shared between ClickUp and your application.

The verification process:
1. ClickUp sends a webhook request with an `X-Signature` header
2. Your application retrieves the webhook secret from the database
3. The application computes the expected signature using HMAC-SHA256
4. The computed signature is compared with the provided signature using a timing-safe comparison
5. If the signatures match, the webhook is authentic and processing continues

### Registering the Middleware

To enable webhook signature verification, register the middleware in your application. You have two options:

**Option 1: Register globally in `bootstrap/app.php` (Laravel 11+)**

```php
use Mindtwo\LaravelClickUpApi\Http\Middleware\VerifyClickUpWebhookSignature;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'clickup.webhook' => VerifyClickUpWebhookSignature::class,
        ]);
    })
    // ...
    ->create();
```

**Option 2: Register in `app/Http/Kernel.php` (Laravel 10)**

```php
protected $middlewareAliases = [
    // ... other middleware
    'clickup.webhook' => \Mindtwo\LaravelClickUpApi\Http\Middleware\VerifyClickUpWebhookSignature::class,
];
```

### Applying the Middleware to Routes

Once registered, apply the middleware to your webhook route:

```php
// In your routes/web.php or routes/api.php
Route::post('/webhooks/clickup', [WebhookController::class, 'handle'])
    ->middleware('clickup.webhook');
```

Note: The package automatically registers webhook routes if `webhook.enabled` is set to `true` in the config file. The middleware is applied automatically to these routes.

### Webhook Secret Storage

When you create a webhook using ClickUp's API, ClickUp generates a unique secret for that webhook. This secret must be stored in your database to enable signature verification.

The package automatically captures and stores webhook secrets in the `clickup_webhooks` table when creating webhooks via the `webhooks()->create()` or `webhooks()->createManaged()` methods:

```php
use Mindtwo\LaravelClickUpApi\Facades\ClickUpClient as ClickUp;

// Create a webhook - secret is automatically captured
$response = ClickUp::webhooks()->create($workspaceId, [
    'endpoint' => 'https://your-app.com/webhooks/clickup',
    'events' => ['taskCreated', 'taskUpdated'],
]);

// The webhook secret is stored in the database
$webhook = $response['webhook'];
// Secret is available at: $webhook['secret']
```

### Security Best Practices

1. **Always use HTTPS**: Configure your webhook endpoint to use HTTPS to prevent man-in-the-middle attacks
2. **Keep secrets secure**: Never commit webhook secrets to version control or expose them in logs
3. **Validate webhook IDs**: The middleware checks that the webhook ID exists in your database before processing
4. **Use timing-safe comparison**: The package uses `hash_equals()` for constant-time signature comparison to prevent timing attacks
5. **Monitor failed attempts**: Failed signature verifications are logged with IP addresses for security monitoring
6. **Disable inactive webhooks**: Use the health monitoring system to automatically disable failing webhooks

### Troubleshooting Signature Verification

If webhook signature verification is failing:

1. **Check the webhook exists**: Ensure the webhook is registered in your `clickup_webhooks` table
2. **Verify the secret is stored**: Check that the `secret` column is not null for the webhook
3. **Check the X-Signature header**: Ensure ClickUp is sending the `X-Signature` header
4. **Review logs**: Check your Laravel logs for signature verification warnings
5. **Test with ClickUp API**: Use ClickUp's webhook testing feature to verify your endpoint

Example log entry for a failed verification:

```
[2025-12-19 10:30:45] local.WARNING: Invalid ClickUp webhook signature
{
    "webhook_id": "wh_abc123",
    "ip": "192.168.1.1"
}
```

## Webhook Health Monitoring

This package includes automatic webhook health monitoring to ensure your ClickUp webhooks remain active and functional. The system periodically checks the health status of all registered webhooks and takes appropriate actions when issues are detected.

### Health Status

ClickUp webhooks can have three health statuses:

- **Active**: Webhook is healthy and receiving events
- **Failing**: Webhook returns unsuccessful HTTP codes or exceeds 7 seconds response time
- **Suspended**: Webhook has reached 100 failed events and no longer receives events from ClickUp

### Automatic Health Checks

To enable automatic health monitoring, add the following to your application's `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Check ClickUp webhook health every hour
    $schedule->job(\Mindtwo\LaravelClickUpApi\Jobs\CheckWebhookHealth::class)
        ->hourly()
        ->name('clickup-webhook-health-check')
        ->withoutOverlapping();
}
```

The health check job will:
- Query the ClickUp API to fetch current webhook health status
- Sync health data (status and fail count) to your local database
- Log warnings when webhook status changes
- Automatically disable webhooks that become failing or suspended

### Manual Webhook Recovery

If a webhook becomes failing or suspended, you can manually recover it using the recovery command:

**Recover a single webhook:**
```bash
php artisan clickup:webhook-recover {webhook_id}
```

**Recover all failed/suspended webhooks:**
```bash
php artisan clickup:webhook-recover --all
```

The recovery command will:
- Reactivate the webhook via ClickUp API by setting its status to active
- Reset the fail count to 0
- Enable the webhook in your local database
- Provide console feedback on success or failure

**Example output:**
```
Found 2 webhook(s) to recover.

Attempting to recover webhook: wh_abc123
  Status: failing
  Endpoint: https://your-app.com/webhooks/clickup
  Fail count: 45
  ✓ Successfully recovered webhook wh_abc123

Attempting to recover webhook: wh_def456
  Status: suspended
  Endpoint: https://your-app.com/webhooks/clickup
  Fail count: 100
  ✓ Successfully recovered webhook wh_def456

Recovery complete: 2 successful, 0 failed
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed
recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report
security vulnerabilities.

## Credits

- [mindtwo GmbH](https://github.com/mindtwo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
