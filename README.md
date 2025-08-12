# Laravel ClickUp API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-clickup-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-clickup-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-clickup-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-clickup-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-clickup-api/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mindtwo/laravel-clickup-api/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-clickup-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-clickup-api)

This Laravel package provides a convenient and efficient way to integrate your Laravel application with ClickUp, a popular project management tool. Designed for ease of use, it offers a seamless connection, enabling your Laravel application to interact with ClickUp's APIs for task management, project tracking, and more. Whether you're looking to automate project updates, synchronize tasks, or enhance team collaboration, this package streamlines the process, making it easier to keep your projects organized and up-to-date directly from your Laravel application.

**Please note:** that this package currently supports a limited set of ClickUp endpoints, specifically those related to Tasks, Attachments, and Custom Fields. We are actively working to expand our coverage of ClickUp's API to include more endpoints and functionalities. If you require integration with aspects of ClickUp not yet covered by our package, we appreciate your patience and welcome contributions or suggestions to enhance our offering.


## Installation

You can install the package via composer:

```bash
composer require mindtwo/laravel-clickup-api
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-clickup-api-config"
```

This is the contents of the published [config file](config/clickup.php).

## ENV Configuration
To ensure the proper functioning of this Laravel package with ClickUp, you must provide your ClickUp API key by setting the `CLICKUP_API_KEY` constant in your application's environment configuration. Here's how to find your ClickUp API key:

1. Log in to your ClickUp account and navigate to your profile settings by clicking on your profile picture in the bottom left corner.
2. Select "Apps" from the sidebar menu.
3. Scroll to the "API" section and find the "Generate" button to create a new API key. If you've already generated one, it will be displayed here.
4. Copy the API key and add it to your Laravel `.env` file as follows:
   ```
   CLICKUP_API_KEY=your_clickup_api_key_here
   ```

Make sure to replace `your_clickup_api_key_here` with the actual API key you obtained from ClickUp. This step is crucial for authenticating your Laravel application's requests to ClickUp's API.

## Usage

### Task Endpoint Usage

The `Task` class within the Laravel ClickUp API package provides a simple interface for interacting with ClickUp's Task-related API endpoints. This class allows you to list, view, create, update, and delete tasks within ClickUp, directly from your Laravel application. Here's a quick overview of how to use it:

- **List Tasks**: Retrieve all tasks within a specific list by providing the list ID.
- **Show Task Details**: Get detailed information about a specific task using its task ID.
- **Create Task**: Create a new task within a list by providing the list ID and an array of task details.
- **Update Task**: Update an existing task by providing the task ID and the details to be updated.
- **Delete Task**: Delete a task using its task ID.

#### How to Use:

1. **Create a Task**: To create a new task within a list, you can use the `create` method. Pass the list ID where the task should be created and an array of data that specifies the task details.

   ```php
   $taskDetails = [
       'name' => 'New Task', // Mandatory
       'description' => 'Task description', // Optional
       // Add other task details as needed
   ];

   $task = app(Mindtwo\LaravelClickUpApi\Endpoints\Task::class)->create($listId, $taskDetails);
   ```

2. **Get Tasks in a List**: To retrieve tasks within a list, use the `index` method with the list ID and an optional array of query parameters to filter the tasks.

   ```php
   $tasks = app(Mindtwo\LaravelClickUpApi\Endpoints\Task::class)->index($listId, []);
   ```

3. **Show Task Details**: To get detailed information about a task, use the `show` method with the task ID.

   ```php
   $task = app(Mindtwo\LaravelClickUpApi\Endpoints\Task::class)->show($taskId);
   ```

4. **Update a Task**: To update an existing task, use the `update` method with the task ID and an array of data with the details you wish to update.

   ```php
   $updatedDetails = [
       'name' => 'Updated Task Name',
       // Other task details you want to update
   ];

   $updatedTask = app(Mindtwo\LaravelClickUpApi\Endpoints\Task::class)->update($taskId, $updatedDetails);
   ```

5. **Delete a Task**: To delete a task, use the `delete` method with the task ID.

   ```php
   $response = app(Mindtwo\LaravelClickUpApi\Endpoints\Task::class)->delete($taskId);
   ```

These examples demonstrate the fundamental operations you can perform on tasks within ClickUp through your Laravel application, providing a powerful way to integrate task management functionalities into your workflow.

### Attachment Endpoint Usage
The `Attachment` class within this Laravel package facilitates the creation of attachments in tasks on ClickUp. By utilizing the `create` method, users can easily upload files and associate them with a specific task by its ID. This functionality enhances the task management process, allowing for a more detailed and resource-rich task structure.

To use this endpoint, first ensure that you have a task ID (`$taskId`) where you want to attach a file, and prepare the data (`$data`) according to the ClickUp API specifications for attachments. The `$data` should include the file information structured in a way that's compatible with ClickUp's expectations for attachment uploads.

Here's a basic example on how to use the `Attachment` endpoint:

```php
<?php

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
app(Mindtwo\LaravelClickUpApi\Endpoints\Attachment::class)->create($taskId, $data);
```

This simple interface abstracts away the complexity of dealing with multipart file uploads and the ClickUp API, allowing you to focus on building your application. Remember to replace `'your_task_id_here'` with the actual ID of the task you're targeting and to adjust the `$data` array with the correct file path and other necessary information as per your requirements.


### Custom Fields Endpoint Usage
The `CustomField` class within our Laravel ClickUp API package serves as a dedicated endpoint for interacting with custom fields in ClickUp. Custom fields are pivotal in tailoring ClickUp's lists to your project's specific requirements, allowing for the addition of unique data fields to tasks. Utilizing the `CustomField` class, you can effortlessly retrieve all custom fields associated with a specific list and create custom field values for tasks.

#### Retrieving Custom Fields

Here's a quick guide on how to use the `CustomField` endpoint to fetch custom fields:

```php
// Import the CustomField class at the top of your PHP file
use Mindtwo\LaravelClickUpApi\Endpoints\CustomField;

// Assuming you have a list ID, you can retrieve its custom fields like so:
$listId = 'your_list_id_here'; // Replace with your actual list ID

// Fetch the custom fields for the specified list
$customFields = app(CustomField::class)->show($listId);

// $customFields now contains the response from ClickUp API
```

#### Creating Custom Field Values

You can also create custom field values for specific tasks using the `create` method:

```php
// Import the CustomField class at the top of your PHP file
use Mindtwo\LaravelClickUpApi\Endpoints\CustomField;

// Parameters needed for creating a custom field value
$taskId = 'your_task_id_here'; // Replace with your actual task ID
$fieldId = 'your_field_id_here'; // Replace with your actual field ID
$value = 'your_field_value'; // The value you want to set for the custom field

// Create a custom field value for the specified task
$response = app(CustomField::class)->create($taskId, $fieldId, $value);

// $response now contains the response from ClickUp API
```

Ensure you replace the placeholder values with your actual IDs. The `create` method allows you to set custom field values for tasks, providing flexibility in managing task-specific data within your ClickUp workspace.

### ListCustomFieldsCommand
The ListCustomFieldsCommand class is a Laravel console command provided by the Laravel ClickUp API package, enabling users to list all available custom fields for a specified list in ClickUp. By executing the command php artisan clickup:list-custom-fields, users can either provide a list ID or mapping key directly via the --list option or they will be prompted to enter it. This command is particularly useful for developers and administrators who need to quickly view the custom field configurations within their ClickUp lists, including details such as field ID, name, type, configuration options, creation date, visibility to guests, and whether the field is required. The command outputs this information in a well-organized table format, making it easy to read and analyze directly from the terminal.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [mindtwo GmbH](https://github.com/mindtwo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
