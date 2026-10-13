# Configuration

All configuration is in `config/escalated.php`.

For opt-in department visibility, account associations, account restrictions, and targeted SLAs, see [Ticket access and routing](ticket-access-and-routing.md). All access restrictions and targeted SLA selection are off by default.

## Hosting Mode

```php
'mode' => env('ESCALATED_MODE', 'self-hosted'),
```

Options: `self-hosted`, `synced`, `cloud`

## Routes

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'support',
    'middleware' => ['web', 'auth'],
    'admin_middleware' => ['web', 'auth'],
],
```

## Tickets

```php
'tickets' => [
    'allow_customer_close' => true,
    'auto_close_resolved_after_days' => 7,
    'max_attachments_per_reply' => 5,
    'max_attachment_size_kb' => 10240,
],
```

## Priorities

```php
'priorities' => ['low', 'medium', 'high', 'urgent', 'critical'],
'default_priority' => 'medium',
```

## Status Transitions

Define which status transitions are allowed:

```php
'transitions' => [
    'open' => ['in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
    'in_progress' => ['open', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
    'waiting_on_customer' => ['open', 'in_progress', 'resolved', 'closed'],
    'waiting_on_agent' => ['open', 'in_progress', 'resolved', 'closed'],
    'escalated' => ['in_progress', 'resolved', 'closed'],
    'resolved' => ['closed', 'reopened'],
    'closed' => ['reopened'],
    'reopened' => ['open', 'in_progress'],
],
```

## SLA

```php
'sla' => [
    'enabled' => true,
    'business_hours_only' => false,
    'business_hours' => [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'UTC',
        'days' => [1, 2, 3, 4, 5], // Mon-Fri
    ],
],
```

## Notifications

```php
'notifications' => [
    'channels' => ['mail', 'database'],
    'webhook_url' => env('ESCALATED_WEBHOOK_URL'),
],
```

## Storage

```php
'storage' => [
    'disk' => 'public',
    'path' => 'escalated/attachments',
],
```

## Authorization

```php
'authorization' => [
    'admin_gate' => 'escalated-admin',
    'agent_gate' => 'escalated-agent',
],
```

## Cloud/Synced Mode

```php
'hosted' => [
    'api_url' => env('ESCALATED_API_URL', 'https://cloud.escalated.dev/api/v1'),
    'api_key' => env('ESCALATED_API_KEY'),
],
```
