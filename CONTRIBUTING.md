# Contributing to Laravel ClickUp API

Thank you for considering contributing to the Laravel ClickUp API package! This
guide will help you understand how to contribute effectively to this project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Adding New Endpoints](#adding-new-endpoints)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Reporting Issues](#reporting-issues)

## Code of Conduct

This project follows a code of conduct to ensure a welcoming environment for all
contributors. By participating, you are expected to:

- Be respectful and inclusive
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

You can see the Code of Conduct in detail [here](CODE_OF_CONDUCT.md).

## How Can I Contribute?

There are many ways to contribute to this project:

### 1. Report Bugs

If you find a bug, please create an issue with:

- A clear, descriptive title
- Steps to reproduce the problem
- Expected behavior vs. actual behavior
- Your environment (PHP version, Laravel version, package version)
- Any error messages or stack traces

### 2. Suggest Enhancements

Have an idea for a new feature or improvement? Create an issue describing:

- The problem you're trying to solve
- Your proposed solution
- Any alternative solutions you've considered
- How this benefits other users

### 3. Add New ClickUp API Endpoints

The package doesn't cover all ClickUp API endpoints yet. You can help by:

- Implementing missing endpoints (
  see [Adding New Endpoints](#adding-new-endpoints))
- Ensuring the implementation follows our patterns
- Adding comprehensive documentation

### 4. Improve Documentation

Documentation improvements are always welcome:

- Fix typos or unclear explanations
- Add more usage examples
- Improve code comments
- Update the README with better examples

### 5. Write Tests

Help improve test coverage by:

- Writing tests for existing features
- Adding edge case tests
- Improving test documentation

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git

### Initial Setup

1. **Fork the repository** on GitHub

2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/laravel-clickup-api.git
   cd laravel-clickup-api
   ```

3. **Install dependencies**:
   ```bash
   composer install
   ```

4. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

### Running Tests

Run the test suite to ensure everything works:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

### Code Analysis

Run static analysis to catch potential issues:

```bash
composer analyse
```

## Coding Standards

This project follows strict coding standards to maintain consistency and
quality.

### PHP Standards

- Follow **PSR-12** coding style
- Use **PHP 8.3+ features** where appropriate (typed properties, union types,
  etc.)
- Write **type-safe code** with proper type hints

### Code Formatting

We use **Laravel Pint** for automatic code formatting.

Before committing, format your code:

```bash
composer format
```

This will automatically fix style issues according to Laravel's coding
standards.

### Documentation Standards

Every public method must have a PHPDoc block including:

```php
/**
 * Brief description of what the method does.
 *
 * Longer description if needed, explaining behavior,
 * constraints, or important notes.
 *
 * @param int|string $paramName Description of parameter
 * @param array $data Array structure:
 *                    - key1 (type, required/optional): Description
 *                    - key2 (type, required/optional): Description
 *
 * @return \Illuminate\Http\Client\Response
 * 
 * @throws ExceptionType When this exception is thrown
 */
public function methodName(int|string $paramName, array $data): \Illuminate\Http\Client\Response
{
    // Implementation
}
```

### Naming Conventions

- **Classes**: PascalCase (e.g., `TaskDependency`)
- **Methods**: camelCase (e.g., `createTask`)
- **Variables**: camelCase (e.g., `$taskId`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `API_VERSION`)

## Adding New Endpoints

When adding new ClickUp API endpoints, follow these steps:

### 1. Create the Endpoint Class

Create a new file in `src/Endpoints/`:

```php
<?php

namespace Mindtwo\LaravelClickUpApi\Endpoints;

use Mindtwo\LaravelClickUpApi\ClickUpClient;

class YourEndpoint
{
    public function __construct(protected ClickUpClient $api)
    {
        //
    }

    /**
     * Method documentation here.
     *
     * @param int|string $id The resource ID
     * @param array $data Request data
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function create(int|string $id, array $data): \Illuminate\Http\Client\Response
    {
        return $this->api->client->post(
            sprintf('/resource/%s', $id),
            $data
        );
    }
}
```

### 2. Follow Existing Patterns

- **Constructor**: Always use dependency injection with `ClickUpClient`
- **HTTP Methods**: Use the client's methods (`get`, `post`, `put`, `delete`)
- **URL Building**: Use `sprintf()` for URL construction
- **Return Types**: Return the raw `Response` object from HTTP client
- **Type Hints**: Use `int|string` for IDs to support both formats

### 3. Standard CRUD Methods

If creating a resource endpoint, implement these methods:

- `index()` - List resources
- `show($id)` - Get single resource
- `create($parentId, $data)` - Create new resource
- `update($id, $data)` - Update existing resource
- `delete($id)` - Delete resource

### 4. Add Comprehensive PHPDocs

Document all parameters, especially complex arrays:

```php
/**
 * @param array $data Task data including:
 *                    - name (string, required): Task name
 *                    - description (string, optional): Task description
 *                    - assignees (array, optional): Array of user IDs
 */
```

### 5. Add Validation Where Needed

Add validation for critical operations:

```php
if (empty($data['name'])) {
    throw new InvalidArgumentException('Name is required.');
}
```

### 6. Update Documentation

Add usage examples to the README.md:

```php
### YourEndpoint Usage

Description of what this endpoint does.

```php
use Mindtwo\LaravelClickUpApi\Endpoints\YourEndpoint;

$result = app(YourEndpoint::class)->create($id, [
    'name' => 'Example',
    'description' => 'Description here',
]);
```

```

## Testing

### Writing Tests

Create test files in the `tests/` directory:

```php
<?php

use Mindtwo\LaravelClickUpApi\Endpoints\YourEndpoint;

it('can create a resource', function () {
    $endpoint = app(YourEndpoint::class);

    $response = $endpoint->create('123', [
        'name' => 'Test Resource',
    ]);

    expect($response)->not->toBeNull();
});
```

### Test Structure

- Use **Pest PHP** testing framework
- Group related tests using `describe()` blocks
- Use descriptive test names
- Test both success and failure scenarios
- Mock API responses when possible

### Running Specific Tests

Run a specific test file:

```bash
./vendor/bin/pest tests/YourTest.php
```

Run tests matching a pattern:

```bash
./vendor/bin/pest --filter="resource"
```

## Submitting Changes

### Commit Message Guidelines

Write clear, descriptive commit messages:

```
Add support for ClickUp Goals endpoint

- Implement Goals CRUD operations
- Add validation for goal creation
- Include PHPDoc documentation
- Add usage examples to README
```

**Format:**

- First line: Brief summary (50 chars or less)
- Blank line
- Detailed description using bullet points
- Reference issue numbers if applicable (`Fixes #123`)

### Pull Request Process

1. **Ensure all tests pass**:
   ```bash
   composer test
   composer analyse
   ```

2. **Format your code**:
   ```bash
   composer format
   ```

3. **Update documentation** if needed:
    - Update README.md with new features
    - Add PHPDoc comments
    - Update CHANGELOG.md

4. **Create a Pull Request** with:
    - Clear title describing the change
    - Detailed description of what changed and why
    - Link to related issues
    - Screenshots if UI-related

5. **Address review feedback**:
    - Respond to comments
    - Make requested changes
    - Push updates to your branch

### PR Checklist

Before submitting, ensure:

- [ ] All tests pass (`composer test`)
- [ ] Code is formatted (`composer format`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] New features have tests
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated (if applicable)
- [ ] Commit messages are clear
- [ ] No merge conflicts with main branch

## Reporting Issues

### Before Creating an Issue

1. **Search existing issues** to avoid duplicates
2. **Check the documentation** to ensure it's not a usage question
3. **Test with the latest version** to see if it's already fixed

### Creating a Good Issue

**For Bug Reports:**

```markdown
## Bug Description

Clear description of the bug

## Steps to Reproduce

1. First step
2. Second step
3. See error

## Expected Behavior

What should happen

## Actual Behavior

What actually happens

## Environment

- PHP Version: 8.3.0
- Laravel Version: 12.x
- Package Version: 1.1.0

## Error Messages
```

Paste error messages or stack traces here

```

## Additional Context
Any other relevant information
```

**For Feature Requests:**

```markdown
## Feature Description

Clear description of the feature

## Problem It Solves

What problem does this solve?

## Proposed Solution

How should this work?

## Alternative Solutions

Other ways to solve this

## Additional Context

Any other relevant information
```

## Security Vulnerabilities

**Do not** create public issues for security vulnerabilities.

Instead, please email security concerns to the maintainers directly. See
our [Security Policy](SECURITY.md) for details.

## Questions?

If you have questions about contributing:

1. Check the [README.md](README.md) for package usage
2. Look at existing code for patterns
3. Create an issue on GitHub (discussions are disabled)
4. Reach out to the maintainers

## Recognition

All contributors will be at least recognized in the
project's [Contributors](../../contributors) listing.

## License

By contributing to this project, you agree that your contributions will be
licensed under the [MIT License](LICENSE).

---

**Thank you for contributing to `mindtwo/laravel-clickup-api`!**

Your efforts help make this package better for everyone. <3
