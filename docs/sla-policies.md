# SLA Policies

SLA (Service Level Agreement) policies define response and resolution time targets per priority level.

Policies can optionally target a department, ticket type, or both. Enable `escalated.sla.targeted` to select the most specific matching policy before falling back to the global default. See [targeted SLA configuration and precedence](ticket-access-and-routing.md#targeted-slas).

## Creating SLA Policies

In the admin panel (`/support/admin/sla-policies`), create policies with:

- **Name** — Policy identifier
- **First Response Hours** — Target hours for first agent response, per priority
- **Resolution Hours** — Target hours for resolution, per priority
- **Business Hours Only** — Whether to count only business hours
- **Is Default** — Auto-attach to new tickets

## How SLA Works

1. When a ticket is created, the `AttachSlaPolicy` listener attaches the default SLA policy
2. Due dates are calculated based on ticket priority and policy hours
3. The `escalated:check-sla` command (run every minute) checks for breaches
4. When a breach occurs, `SlaBreached` event is dispatched
5. Warnings are dispatched when breach is approaching (configurable threshold)

## Business Hours

Configure business hours in `config/escalated.php`:

```php
'sla' => [
    'business_hours_only' => false,
    'business_hours' => [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'America/New_York',
        'days' => [1, 2, 3, 4, 5], // Monday through Friday
    ],
],
```

When `business_hours_only` is true, due dates skip nights and weekends.

## Per-Priority Targets

SLA policies define separate targets per priority:

```php
'first_response_hours' => [
    'low' => 24,
    'medium' => 8,
    'high' => 4,
    'urgent' => 2,
    'critical' => 1,
],
'resolution_hours' => [
    'low' => 72,
    'medium' => 48,
    'high' => 24,
    'urgent' => 8,
    'critical' => 4,
],
```

## Scheduling

Add to your `app/Console/Kernel.php`:

```php
$schedule->command('escalated:check-sla')->everyMinute();
```

Or enable auto-scheduling in config:

```php
'scheduling' => [
    'auto_register' => true,
],
```
