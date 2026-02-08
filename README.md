# Escalated for Laravel

A full-featured, embeddable support ticket system for Laravel. Drop it into any app — get a complete helpdesk with SLA tracking, escalation rules, agent workflows, and a customer portal. No external services required.

**Three hosting modes.** Run entirely self-hosted, sync to a central cloud for multi-app visibility, or proxy everything to the cloud. Switch modes with a single config change.

## Features

- **Ticket lifecycle** — Create, assign, reply, resolve, close, reopen with configurable status transitions
- **SLA engine** — Per-priority response and resolution targets, business hours calculation, automatic breach detection
- **Escalation rules** — Condition-based rules that auto-escalate, reprioritize, reassign, or notify
- **Agent dashboard** — Ticket queue with filters, bulk actions, internal notes, canned responses
- **Customer portal** — Self-service ticket creation, replies, and status tracking
- **Admin panel** — Manage departments, SLA policies, escalation rules, tags, and view reports
- **File attachments** — Drag-and-drop uploads with configurable storage and size limits
- **Activity timeline** — Full audit log of every action on every ticket
- **Email notifications** — Configurable per-event notifications with webhook support
- **Department routing** — Organize agents into departments with auto-assignment (round-robin)
- **Tagging system** — Categorize tickets with colored tags
- **Inertia.js + Vue 3 UI** — Shared frontend via [`@escalated-dev/escalated`](https://github.com/escalated-dev/escalated)

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- Node.js 18+ (for frontend assets)

## Quick Start

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Add the `Ticketable` interface to your User model:

```php
use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;

class User extends Authenticatable implements Ticketable
{
    use HasTickets;
}
```

Define authorization gates in a service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('escalated-admin', fn ($user) => $user->is_admin);
Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
```

Visit `/support` — you're live.

## Frontend Integration

Escalated ships a Vue component library (`@escalated-dev/escalated`) and default page templates. You have two options:

### Option A: Use Default Pages (Quick Start)

Resolve Escalated pages from the npm package in your `app.ts`:

```ts
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const escalatedPages = import.meta.glob(
    '../../node_modules/@escalated-dev/escalated/src/pages/**/*.vue',
);

createInertiaApp({
    resolve: (name) => {
        if (name.startsWith('Escalated/')) {
            const path = name.replace('Escalated/', '');
            return resolvePageComponent(
                `../../node_modules/@escalated-dev/escalated/src/pages/${path}.vue`,
                escalatedPages,
            );
        }
        return resolvePageComponent(`./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'));
    },
    // ...
});
```

### Option B: Custom Pages (Recommended)

Create your own page files in `resources/js/Pages/Escalated/` that use your app's layout. Import individual components from `@escalated-dev/escalated`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { TicketList, TicketFilters, StatusBadge } from '@escalated-dev/escalated';
</script>

<template>
    <AuthenticatedLayout>
        <TicketList :tickets="tickets" />
    </AuthenticatedLayout>
</template>
```

Since local pages in `Pages/Escalated/` match the component names the controllers render, Inertia's standard glob resolver picks them up automatically — no special resolver logic needed.

### Available Components

| Component | Description |
|-----------|-------------|
| `ActivityTimeline` | Full audit log of ticket events |
| `AssigneeSelect` | Agent assignment dropdown |
| `AttachmentList` | File attachment display |
| `FileDropzone` | Drag-and-drop file upload |
| `PriorityBadge` | Priority level indicator |
| `ReplyComposer` | Rich text reply editor |
| `ReplyThread` | Chronological message thread |
| `SlaTimer` | SLA countdown display |
| `StatsCard` | Metric card for dashboards |
| `StatusBadge` | Ticket status indicator |
| `TagSelect` | Tag picker with colors |
| `TicketFilters` | Search and filter controls |
| `TicketList` | Paginated ticket table |
| `TicketSidebar` | Ticket metadata sidebar |

### Shared Inertia Props

Escalated automatically shares data to all Inertia pages via `page.props.escalated`:

```js
page.props.escalated = {
    prefix: 'support',     // Route prefix from config
    is_agent: true,        // Current user can access agent views
    is_admin: false,       // Current user can access admin views
}
```

Use these to conditionally show nav links or restrict UI elements.

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
```

## Scheduling

Add these to your scheduler for SLA and escalation automation:

```php
// app/Console/Kernel.php or routes/console.php
Schedule::command('escalated:check-sla')->everyMinute();
Schedule::command('escalated:evaluate-escalations')->everyFiveMinutes();
Schedule::command('escalated:close-resolved')->daily();
Schedule::command('escalated:purge-activities')->weekly();
```

## Configuration

All config lives in `config/escalated.php`. Key options:

```php
'mode' => 'self-hosted',              // self-hosted | synced | cloud
'user_model' => App\Models\User::class,
'table_prefix' => 'escalated_',
'default_priority' => 'medium',

'routes' => [
    'prefix' => 'support',
    'middleware' => ['web', 'auth'],
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

See the [full configuration reference](docs/configuration.md).

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

[Full events documentation →](docs/events.md)

## Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/support` | GET | Customer ticket list |
| `/support/create` | GET | New ticket form |
| `/support/{ticket}` | GET | Ticket detail |
| `/support/agent` | GET | Agent dashboard |
| `/support/agent/tickets` | GET | Agent ticket queue |
| `/support/agent/tickets/{ticket}` | GET | Agent ticket view |
| `/support/admin/reports` | GET | Admin reports |
| `/support/admin/departments` | GET | Department management |
| `/support/admin/sla-policies` | GET | SLA policy management |
| `/support/admin/escalation-rules` | GET | Escalation rule management |
| `/support/admin/tags` | GET | Tag management |
| `/support/admin/canned-responses` | GET | Canned response management |

All routes use the configurable prefix (default: `support`).

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Customization](docs/customization.md)
- [Events](docs/events.md)
- [SLA Policies](docs/sla-policies.md)
- [Escalation Rules](docs/escalation-rules.md)
- [Hosting Modes](docs/hosting-modes.md)

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT
