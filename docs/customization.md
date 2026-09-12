# Customization

## Publishing Assets

### Customer UI Only
```bash
php artisan vendor:publish --tag=escalated-client-assets
```

### Agent & Admin UI
```bash
php artisan vendor:publish --tag=escalated-admin-assets
```

### Email Templates
```bash
php artisan vendor:publish --tag=escalated-views
```

### Configuration
```bash
php artisan vendor:publish --tag=escalated-config
```

## Custom User Model

Set your user model in config:

```php
'user_model' => App\Models\User::class,
```

Your model must implement `Ticketable` and use `HasTickets`:

```php
use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;

class User extends Authenticatable implements Ticketable
{
    use HasTickets;
}
```

## Custom Authorization

Define gates in your service provider:

```php
Gate::define('escalated-admin', function ($user) {
    return $user->hasRole('admin');
});

Gate::define('escalated-agent', function ($user) {
    return $user->hasRole('agent') || $user->hasRole('admin');
});
```

## Table Prefix

Change the database table prefix:

```php
'table_prefix' => 'support_', // Default: 'escalated_'
```

## Database Connection

By default Escalated's tables live on whatever your application's default
connection is. Name a different one to keep them somewhere else — a schema
shared with a legacy system, a multi-tenant split, a separate reporting store,
or simply out of your primary database:

```php
'connection' => 'support', // Default: null (your app's default connection)
```

or in `.env`:

```dotenv
ESCALATED_DB_CONNECTION=support
```

The connection name is whatever you called it in `config/database.php`.

This moves Escalated's models, migrations, query-builder reads and
transactions together. It deliberately does **not** move your users table:
that belongs to your application, and Escalated follows your user model to
wherever it already lives.

### How the two sides stay connected

Escalated stores host user ids as plain, unconstrained columns, so there is no
foreign key that would have to span the boundary. Relations that point at a
single host user — a ticket's requester, its assignee, a reply's author, a
ticket subject — are two queries either way and work unchanged.

The four relations where an Escalated pivot table joins your users table
(department agents, role members, skill agents, ticket followers) are the one
shape that a single SQL statement cannot express across two connections.
Escalated resolves those in two steps when the connections differ — read the
pivot, then load the users by key — so `->agents`, `->followers`,
`withCount('agents')`, `attach()`, `sync()` and `detach()` all behave the same
as before. On a single connection nothing changes: the ordinary join is still
issued.

### Changing it later

Setting this on an existing install does not move any data. Migrate the tables
yourself, or run the package migrations against the new connection and copy the
rows across, before pointing Escalated at it.

## Custom Notification Channels

Override which channels notifications use:

```php
'notifications' => [
    'channels' => ['mail', 'database', 'slack'],
],
```

## Webhooks

Send events to an external URL:

```php
'notifications' => [
    'webhook_url' => 'https://your-app.com/webhooks/escalated',
],
```

Webhook payload:
```json
{
    "event": "ticket.created",
    "payload": { ... },
    "timestamp": "2026-02-07T12:00:00Z"
}
```

## Custom Status Transitions

Control which statuses can transition to which:

```php
'transitions' => [
    'open' => ['in_progress', 'closed'],
    'in_progress' => ['resolved'],
    'resolved' => ['closed', 'reopened'],
],
```

## Custom Ticket Actions

Host applications can add custom buttons to the agent ticket screen and handle
clicks with normal Laravel events and listeners.

Register actions in `config/escalated.php`:

```php
'ticket_actions' => [
    'actions' => [
        [
            'key' => 'sync-crm',
            'label' => 'Sync CRM',
            'variant' => 'primary',
            'confirmation' => 'Sync this ticket to the CRM?',
            'metadata' => ['icon' => 'refresh-cw'],
        ],
    ],
],
```

For richer visibility rules, use an action class:

```php
use Escalated\Laravel\Contracts\TicketAction;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;

class SyncCrmTicketAction implements TicketAction
{
    public function key(): string
    {
        return 'sync-crm';
    }

    public function label(Ticket $ticket, Authenticatable $user): string
    {
        return 'Sync CRM';
    }

    public function visible(Ticket $ticket, Authenticatable $user): bool
    {
        return $user->can('update', $ticket);
    }

    public function enabled(Ticket $ticket, Authenticatable $user): bool
    {
        return ! ($ticket->metadata['crm_synced'] ?? false);
    }

    public function variant(): string
    {
        return 'primary';
    }

    public function confirmation(Ticket $ticket, Authenticatable $user): ?string
    {
        return 'Sync this ticket to the CRM?';
    }

    public function metadata(Ticket $ticket, Authenticatable $user): array
    {
        return ['icon' => 'refresh-cw'];
    }
}
```

Then reference the class in config:

```php
'ticket_actions' => [
    'actions' => [
        App\Escalated\Actions\SyncCrmTicketAction::class,
    ],
],
```

When the button is clicked, Escalated dispatches
`Escalated\Laravel\Events\TicketCustomActionTriggered`:

```php
use Escalated\Laravel\Events\TicketCustomActionTriggered;
use Illuminate\Support\Facades\Event;

Event::listen(TicketCustomActionTriggered::class, function (TicketCustomActionTriggered $event) {
    if ($event->action !== 'sync-crm') {
        return;
    }

    app(App\Services\CrmSync::class)->syncTicket(
        ticket: $event->ticket,
        user: $event->user,
        options: $event->payload,
    );
});
```

The event exposes `$ticket`, `$action`, `$user`, `$payload`, and `$metadata`.
Custom frontends can read the ticket response's `customActions` or
`custom_actions` collection and submit to each action's `url` with the listed
HTTP `method`.
