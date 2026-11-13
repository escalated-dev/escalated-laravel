# Getting started

## Requirements

- PHP 8.2+ for Laravel 11/12; PHP 8.3+ for Laravel 13
- Laravel 11.39+, 12.x, or 13.x

CI covers Laravel 11/12 on PHP 8.2/8.3, current Laravel 13 on PHP 8.3/8.5,
and the exact Laravel 13.8.0 merchant-host compatibility target on PHP 8.3.
The historical 13.8.0 job checks compatibility; deployments should use a current
security-patched framework release. The test tooling supports Pest 3 and 4,
with Pest 4 selected for Laravel 13.
- Node.js 18+ (for frontend assets)

## Quick Start

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

The install command will offer to automatically configure your User model with the `Ticketable` interface and `HasTickets` trait. If you prefer to do this manually, or if you use a custom user model, add the following:

```php
use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;

class User extends Authenticatable implements Ticketable
{
    use HasTickets;
}
```

Define authorization gates in `App\Providers\AppServiceProvider::boot()` for Laravel 12+, or `App\Providers\AuthServiceProvider::boot()` for Laravel 11 and earlier:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('escalated-admin', fn ($user) => $user->is_admin);
Gate::define('escalated-agent', fn ($user) => $user->is_agent);
```

These global gates are enough for a single-account installation. With
[multiple merchant accounts](#multiple-merchant-accounts) enabled, staff access
also requires a tenant-local seat from the host resolver's `isAgent` / `isAdmin`,
so a global flag never makes someone an agent of an account where they are a customer.

Visit `/support` — you're live.

### UUID / string user keys

Escalated supports both integer and string (UUID/ULID) host-app user keys out of
the box — no code or migration edits required:

- Every user-id parameter is typed `int|string`, and incoming user ids are never
  cast to `int` (so UUIDs aren't corrupted).
- The user-referencing columns Escalated creates (`ticket_followers.user_id`,
  `agent_profiles.user_id`, `tickets.assigned_to`/`requester_id`, the polymorphic
  requester/author/causer columns, the role and skill pivots, etc.) are typed to
  **match your user model's key type automatically**. At migration time Escalated
  reflects the configured `user_model`: an auto-incrementing integer key yields
  `unsignedBigInteger` columns; a `HasUuids`/`HasUlids`/string key yields
  string-compatible columns.

Override the detection with the `user_key_type` config (`'auto'` by default;
`'bigint'`, `'uuid'`, `'ulid'`, or `'string'`):

```php
// config/escalated.php
'user_key_type' => 'uuid',
```

> **Existing installs:** the column type is chosen when a migration runs. Apps
> already migrated (e.g. as `bigint`) keep their columns; switching your user key
> type after installing requires a manual migration.

## Multiple merchant accounts

Self-hosted installations can enable host-resolved tenant isolation for tickets,
attachments, reports, settings, API tokens, background work and broadcasts. The
host supplies account selection, membership checks and per-account agent/admin
seats; without a seat decision staff access is denied. Existing installations must
explicitly assign legacy data before enabling it; cloud/synced modes and plugin
execution do not currently support tenant isolation.

Follow the [tenant integration and upgrade guide](tenancy.md), including the
shared frontend prerequisite, account provisioning and per-account scheduler setup.

## Ticket subjects

A ticket has a **requester** (the person who raised it) and a **subject line**
(free text). Sometimes a ticket is also *about* one or more host-app entities —
a Project, a Customer, an asset — that aren't people. Attach them as ticket
**subjects** so agents see what the ticket concerns and can jump straight to it
in your app.

Make any model attachable by implementing the `TicketSubject` contract. The
`PresentsAsTicketSubject` trait gives sane defaults — override only what you need:

```php
use Escalated\Laravel\Concerns\PresentsAsTicketSubject;
use Escalated\Laravel\Contracts\TicketSubject;

class Project extends Model implements TicketSubject
{
    use PresentsAsTicketSubject;

    public function ticketSubjectSubtitle(): ?string
    {
        return 'Project · '.$this->customer->name;
    }

    public function ticketSubjectUrl(): ?string
    {
        return route('projects.show', $this);
    }

    public function ticketSubjectColor(): ?string
    {
        return '#2563eb';
    }

    public function ticketSubjectIcon(): ?string
    {
        return 'folder';
    }
}
```

Attach, detach, or sync subjects on a ticket — a ticket can reference several:

```php
$ticket->attachSubject($project, role: 'project');
$ticket->attachSubject($customer, role: 'account');
$ticket->syncSubjects([$project, [$customer, 'account']]);
$ticket->detachSubject($project);
```

Each attached subject is serialized onto the ticket as
`{ type, id, role, title, subtitle, url, color, icon }` for the frontend to
render (clickable when `url` is set). `subject_id` is stored as a string, so
integer, UUID, or string-keyed host models all work.

To allow attaching subjects via the agent API (and protect against arbitrary
class resolution from request input), list the permitted models in config:

```php
// config/escalated.php
'ticket_subjects' => [
    'types' => [
        \App\Models\Project::class,
        \App\Models\Customer::class,
    ],
],
```

Programmatic `attachSubject()` works for any model when the allowlist is empty;
the agent API only accepts allowlisted types.
