# Running Escalated for Laravel

## Hosting Modes

### Self-Hosted (default)

Everything stays in your database. No external calls. Full autonomy.

```php
// config/escalated.php
'mode' => 'self-hosted',
```

### Synced

Local database + automatic sync to `cloud.escalated.dev` for unified inbox across multiple apps. If the cloud is unreachable, your app keeps working — events queue and retry.

```php
'mode' => 'synced',
'hosted' => [
    'api_url' => 'https://cloud.escalated.dev/api/v1',
    'api_key' => env('ESCALATED_API_KEY'),
],
```

### Cloud

All ticket data proxied to the cloud API. Your app handles auth and renders UI, but storage lives in the cloud. Supports multiple domains per API key.

```php
'mode' => 'cloud',
```

All three modes share the same controllers, UI, and business logic. The driver pattern handles the rest.

## Publishing Assets

```bash
# Email templates
php artisan vendor:publish --tag=escalated-views

# Config file
php artisan vendor:publish --tag=escalated-config

# Database migrations
php artisan vendor:publish --tag=escalated-migrations

# Translations (sourced from the central escalated-dev/locale package)
php artisan vendor:publish --tag=escalated-lang
```

## Translations

Translation strings for the `escalated` namespace are sourced from the
shared [`escalated-dev/locale`](https://github.com/escalated-dev/escalated-locale)
Composer package, which is consumed by every Escalated host plugin
(Laravel, Rails, NestJS, Django, …) so wording stays consistent across
backends.

The `EscalatedServiceProvider` wires three layers, lowest to highest
precedence:

1. **Central package** — `vendor/escalated-dev/locale/locales/{locale}/...`
   The canonical source.
2. **Plugin-shipped overrides** — `lang/vendor/escalated/{locale}/...` in
   this repository, for Laravel-specific divergences.
3. **Host-app overrides** — `{app}/lang/vendor/escalated/{locale}/...`,
   populated via `php artisan vendor:publish --tag=escalated-lang`. Apps
   edit those files to brand or relocalize Escalated.

Each layer is merged on top of the previous one with
`array_replace_recursive` (PHP groups) and `array_merge` (JSON), so a
layer only needs to define the keys it changes. See
[`lang/README.md`](../lang/README.md) for details.

## Required: schedule the recurring jobs

SLA checks, escalation rules, automations and snooze wake-ups happen only when
these commands run on a schedule. Nothing else runs them.

The simple way: set `ESCALATED_SCHEDULE_AUTO_REGISTER=true` (config
`escalated.scheduling.auto_register`; `escalated:install` writes it for new
installs). Escalated then adds every command below to the Laravel scheduler,
and the server needs only Laravel's cron entry:

```
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

To schedule them yourself instead, leave auto-registration off and add each
one, e.g. in `routes/console.php`:

```php
Schedule::command('escalated:check-sla')->everyMinute();
Schedule::command('escalated:process-delayed-actions')->everyMinute();
Schedule::command('escalated:wake-snoozed-tickets')->everyMinute();
Schedule::command('escalated:evaluate-escalations')->everyFiveMinutes();
Schedule::command('escalated:run-automations')->everyFiveMinutes();
Schedule::command('escalated:close-resolved')->daily();
Schedule::command('escalated:purge-activities')->weekly();
```

| Command | Interval | What stops without it |
|---------|----------|-----------------------|
| `escalated:check-sla` | every minute | SLA breach and warning checks |
| `escalated:process-delayed-actions` | every minute | Delayed workflow actions |
| `escalated:wake-snoozed-tickets` | every minute | Snoozed tickets waking up |
| `escalated:evaluate-escalations` | every five minutes | Escalation rules |
| `escalated:run-automations` | every five minutes | Time-based automations |
| `escalated:close-resolved` | daily | Auto-closing resolved tickets |
| `escalated:purge-activities` | weekly | Activity log retention |

Optional features add their own while they are on: `escalated:plugins:cron`
(SDK plugins), `escalated:slack:process` (Slack), `escalated:close-idle-chats`
and `escalated:cleanup-abandoned-chats` (live chat),
`escalated:newsletters:dispatch` (newsletters) and `escalated:poll-imap`
(inbound email on the IMAP adapter), all every minute. With tenancy on, each
tenant-scoped command runs as `escalated:tenant-run <command>`.

`escalated:check-sla` records a heartbeat in the cache each time it runs (even
with SLAs off). When it is missing or older than 15 minutes, Admin > Settings,
Admin > Reports and the reports dashboard show a warning naming the commands to
schedule (the `scheduler` page prop: `healthy`, `last_run_at`, `message`,
`commands`). Use a cache store that outlives a deploy, or the warning shows
until the next run.

## Public request limits

Guest ticket and widget routes share IP-based request budgets:
60 requests, 5 new tickets/chats, and 30 replies/ratings per minute. Configure
`escalated.guest_rate_limits.requests_per_minute`, `submissions_per_minute`, and
`replies_per_minute` for your deployment (minimum 1). Exceeding a budget returns
HTTP 429 with `Retry-After`. Reading ticket status does not spend the submission
budget. Changing transport, guest token, email or selected merchant does not
reset an IP's budget.

Configure Laravel's trusted proxies correctly so `Request::ip()` identifies the
client, and use a shared cache store for a deployment with multiple nodes. These
limits complement the mobile API's existing rate limit and signed attachment
download limits. They do not verify an email address or expire guest access.

## Configuration

All config lives in `config/escalated.php`. Key options:

```php
'mode' => 'self-hosted',              // self-hosted | synced | cloud
'user_model' => App\Models\User::class,
'table_prefix' => 'escalated_',
'connection' => null,                 // null = your app's default connection
'default_priority' => 'medium',

'routes' => [
    'prefix' => 'support',
    'middleware' => ['web', 'auth'],
    'customer_middleware' => [],      // extra guards on the customer portal and KB
],

'tickets' => [
    'allow_customer_close' => true,
    'auto_close_resolved_after_days' => 7,
],

'sla' => [
    'enabled' => true,
    'business_hours_only' => false,
    'business_hours' => [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'UTC',
        'days' => [1, 2, 3, 4, 5],
    ],
],
```

See the [full configuration reference](configuration.md).

Department visibility, ticket accounts, account restrictions, and targeted SLAs are independent opt-ins. See [ticket access and routing](ticket-access-and-routing.md) for host integration hooks and upgrade guidance.

## Events

Every ticket action dispatches an event you can listen to:

| Event | When |
|-------|------|
| `TicketCreated` | New ticket |
| `TicketStatusChanged` | Status transition |
| `TicketAssigned` | Agent assigned |
| `ReplyCreated` | Public reply added |
| `InternalNoteAdded` | Agent note added |
| `SlaBreached` | SLA deadline missed |
| `TicketEscalated` | Ticket escalated |
| `TicketResolved` | Ticket resolved |
| `TicketClosed` | Ticket closed |

```php
use Escalated\Laravel\Events\TicketCreated;

Event::listen(TicketCreated::class, function ($event) {
    // $event->ticket
});
```

[Full events documentation →](events.md)

## Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/support` | GET | Customer ticket list |
| `/support/create` | GET | New ticket form |
| `/support/{ticket}` | GET | Ticket detail |
| `/support/guest/create` | GET | Guest ticket form |
| `/support/guest/{token}` | GET | Guest ticket view (magic link) |
| `/support/agent` | GET | Agent dashboard |
| `/support/agent/tickets` | GET | Agent ticket queue |
| `/support/agent/tickets/{ticket}` | GET | Agent ticket view |
| `/support/admin/reports` | GET | Admin reports |
| `/support/admin/departments` | GET | Department management |
| `/support/admin/sla-policies` | GET | SLA policy management |
| `/support/admin/escalation-rules` | GET | Escalation rule management |
| `/support/admin/tags` | GET | Tag management |
| `/support/admin/canned-responses` | GET | Canned response management |
| `/support/inbound/mailgun` | POST | Mailgun inbound webhook |
| `/support/inbound/postmark` | POST | Postmark inbound webhook |
| `/support/inbound/ses` | POST | SES/SNS inbound webhook |
| `/support/agent/tickets/bulk` | POST | Bulk actions on multiple tickets |
| `/support/agent/tickets/{ticket}/follow` | POST | Follow/unfollow a ticket |
| `/support/agent/tickets/{ticket}/macro` | POST | Apply a macro to a ticket |
| `/support/agent/tickets/{ticket}/presence` | POST | Update presence on a ticket |
| `/support/agent/tickets/{ticket}/pin/{reply}` | POST | Pin/unpin an internal note |
| `/support/{ticket}/rate` | POST | Submit satisfaction rating |

All routes use the configurable prefix (default: `support`). Inbound webhook routes use the `api` middleware (no auth, no CSRF).

Optional public routes are registered only while their feature is on:
`{prefix}/guest/...` with `escalated.guest.routes_enabled`
(`ESCALATED_GUEST_ROUTES`, default on), `{prefix}/widget/...` with
`escalated.widget.enabled` (`ESCALATED_WIDGET_ENABLED`, default on),
`{prefix}/inbound/slack/{app}` with `escalated.slack.enabled`
(`ESCALATED_SLACK_ENABLED`, default off), and `/escalated/cloud/webhook` once
`escalated.hosted.signing_secret` is set. See
[staff-only installs](configuration.md#staff-only-installs).

## Testing

```bash
composer install
vendor/bin/pest
```
