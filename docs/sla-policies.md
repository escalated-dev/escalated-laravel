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
6. With `escalated.sla.targeted` on, a department change selects the policy again and re-anchors the deadlines on the time of the change (see [targeted SLAs](ticket-access-and-routing.md#targeted-slas))

## Business Hours

When a policy is **Business Hours Only**, its deadlines are counted in a business schedule (admin, `/support/admin/business-hours`): opening hours per weekday in the schedule's time zone, plus holidays. The schedule is chosen as:

1. the policy's `business_schedule_id`,
2. else the ticket department's `business_schedule_id`,
3. else the default schedule (`is_default`),
4. else the configured hours below, exactly as before schedules were used.

How a schedule is counted:

- Hours are wall-clock times in the schedule's zone (UTC when the zone is unknown), counted in real elapsed time, so a window on a daylight-saving change day is an hour shorter or longer. An opening time skipped by spring-forward opens at the change.
- A holiday (one-off, or recurring every year on its month and day) closes the window that opens on that local date, including the part of an overnight window after midnight.
- `24:00` is the next midnight; an end before the start is an overnight window (`22:00` to `06:00`); an equal start and end, an empty start or end, or `enabled: false` closes the day.
- A schedule with no open day counts calendar time.

SLA policy and department saves accept `business_schedule_id` (nullable, must exist); it changes only when sent. Deleting a schedule clears it from the policies and departments that used it.

With no schedule, configure business hours in `config/escalated.php`:

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
