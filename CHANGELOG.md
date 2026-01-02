# Changelog

All notable changes to `laravel-clickup-api` will be documented in this file.

## [2.0.0] - 2025-12-31

### Breaking Changes

#### Configuration File Renamed
- **BREAKING**: Configuration file renamed from `config/clickup.php` to `config/clickup-api.php`
- **Migration Path**: Users upgrading from 1.x must:
  1. Backup existing config: `cp config/clickup.php config/clickup.backup.php`
  2. Delete old config: `rm config/clickup.php`
  3. Publish new config: `php artisan vendor:publish --tag="clickup-api-config"`
  4. Copy values from backup to new config file

#### New Database Tables Required
- **BREAKING**: New database migrations must be run
- Publish migrations: `php artisan vendor:publish --tag="clickup-api-migrations"`
- Run migrations: `php artisan migrate`
- Two new tables will be created:
  - `clickup_webhooks` - Stores webhook registrations and health status
  - `clickup_webhook_deliveries` - Tracks webhook delivery history and idempotency

### Added

#### Webhook System
- **NEW**: Complete webhook infrastructure with signature verification
- **NEW**: `WebhookController` for handling incoming webhook events from ClickUp
- **NEW**: `VerifyClickUpWebhookSignature` middleware for HMAC-SHA256 signature verification
- **NEW**: Automatic webhook secret storage and management
- **NEW**: Idempotency key-based duplicate detection for webhook deliveries
- **NEW**: Delivery tracking with status (received, processed, failed) and processing time metrics

#### Webhook Health Monitoring
- **NEW**: Automated webhook health monitoring system
- **NEW**: `CheckWebhookHealth` job for periodic health status synchronization with ClickUp API (The package does not schedule the job itself, this has to be done by in your app)
- **NEW**: Health status tracking: ACTIVE, FAILING, SUSPENDED
- **NEW**: Automatic webhook deactivation when health status becomes failing or suspended
- **NEW**: Fail count tracking for monitoring delivery success rates

#### Webhook Recovery
- **NEW**: `RecoverWebhookCommand` (php artisan clickup:webhook-recover) for manual webhook recovery
- **NEW**: Support for recovering single webhook by ID or all failed/suspended webhooks with --all flag
- **NEW**: Automatic fail count reset and webhook reactivation via ClickUp API

#### Event System
- **NEW**: 29+ dedicated event classes for different ClickUp webhook events
- **NEW**: Event source tracking (WEBHOOK vs API)
- **NEW**: Base `ClickUpEvent` class that all events extend
- **NEW**: `LogClickUpEvent` listener with configurable logging (enabled/disabled, log level, payload inclusion)
- **NEW**: Events include:
  - Task events: TaskCreated, TaskUpdated, TaskDeleted, TaskStatusUpdated, TaskPriorityUpdated, etc.
  - Time tracking: TaskTimeTrackedUpdated, TaskTimeEstimateUpdated
  - Organizational: FolderCreated, FolderUpdated, SpaceCreated, ListCreated
  - Goals: GoalCreated, GoalUpdated, KeyResultCreated, etc.

#### API Enhancements
- **NEW**: `LazyResponseProxy` for flexible API call execution (immediate or queued)
- **NEW**: `ClickUpApiCallJob` for queuing API calls
- **NEW**: Rate limiting configuration (default: 100 requests/minute)
- **NEW**: Response validation in API call jobs
- **NEW**: Webhooks endpoint with full CRUD operations
- **NEW**: `createManaged()` method for synchronized webhook creation
- **NEW**: Tag and Views endpoints added

#### Models & Database
- **NEW**: `ClickUpWebhook` model with relationships and enum casting
- **NEW**: `ClickUpWebhookDelivery` model for delivery history
- **NEW**: `WebhookHealthStatus` enum (ACTIVE, FAILING, SUSPENDED)
- **NEW**: `EventSource` enum (WEBHOOK, API)
- **NEW**: `WebhookEventType` enum with 29+ event types
- **NEW**: Soft deletes on webhook models
- **NEW**: Foreign key constraints with cascade deletion

#### Developer Experience
- **NEW**: `HandlesTask` trait for task-related helper methods
- **NEW**: Comprehensive webhook security documentation in README
- **NEW**: Detailed upgrade guide from 1.1 to 2.0.0
- **NEW**: Security best practices and troubleshooting guide

### Changed

- **IMPROVED**: Error handling and logging throughout webhook processing
- **IMPROVED**: Webhook route registration moved to separate routes file
- **IMPROVED**: Event class structure - all events now extend base `ClickUpEvent`
- **IMPROVED**: JSON response parsing for webhook data from ClickUp API
- **IMPROVED**: Migration organization with proper tags
- **REFACTORED**: Replaced direct API calls with `LazyResponseProxy` pattern
- **REFACTORED**: Rate limiter moved to correct boot method in service provider
- **UPDATED**: PHP requirement remains 8.2+ (tested with 8.2)
- **ENHANCED**: Command signature formatting for better CLI output

### Fixed

- **FIXED**: RuntimeException import using wrong namespace (was SebastianBergmann\Template\RuntimeException, now using PHP's built-in)
- **FIXED**: Database schema inconsistency - health_status default changed from 'healthy' to 'active' to match enum values
- **FIXED**: Removed unused status column index from webhooks migration
- **FIXED**: Response validation errors in ClickUp API call job
- **FIXED**: Error handling for webhook create, update, delete, and sync operations

### Configuration

New environment variables available (all optional):

```env
# Webhook Configuration
CLICKUP_WEBHOOK_ENABLED=true
CLICKUP_WEBHOOK_PATH=/webhooks/clickup

# Event Logging
CLICKUP_EVENT_LOGGING_ENABLED=true
CLICKUP_EVENT_LOGGING_LEVEL=info
CLICKUP_EVENT_LOGGING_CHANNEL=stack
CLICKUP_EVENT_LOGGING_INCLUDE_PAYLOAD=false

# API Settings
CLICKUP_DEFAULT_WORKSPACE_ID=your_workspace_id
CLICKUP_API_CALLS_QUEUE=default
CLICKUP_API_CALLS_CONNECTION=sync
CLICKUP_API_RATE_LIMIT_PER_MINUTE=100
```

### Upgrade Guide (1.x → 2.0.0)

1. **Update composer.json**: `composer require mindtwo/laravel-clickup-api:^2.0`
2. **Backup configuration**: `cp config/clickup.php config/clickup.backup.php`
3. **Remove old config**: `rm config/clickup.php`
4. **Publish new config**: `php artisan vendor:publish --tag="clickup-api-config"`
5. **Copy configuration values** from backup to new `config/clickup-api.php`
6. **Publish migrations**: `php artisan vendor:publish --tag="clickup-api-migrations"`
7. **Run migrations**: `php artisan migrate`
8. **Update environment variables** (see Configuration section above)
9. **Optional**: Set up webhook health monitoring in `app/Console/Kernel.php`:
   ```php
   $schedule->job(\Mindtwo\LaravelClickUpApi\Jobs\CheckWebhookHealth::class)
       ->hourly()
       ->name('clickup-webhook-health-check')
       ->withoutOverlapping();
   ```

### Security Notes

- Webhook signature verification uses HMAC-SHA256 with timing-safe comparison (`hash_equals`)
- Failed signature verifications are logged with IP addresses for security monitoring
- Webhook secrets are automatically captured and stored from ClickUp API responses
- Always use HTTPS endpoints for webhooks to prevent man-in-the-middle attacks

---
