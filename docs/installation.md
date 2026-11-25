# Installation

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- MySQL / PostgreSQL / SQLite

## Install via Composer

```bash
composer require escalated/escalated-laravel
```

The package uses Laravel's auto-discovery, so the service provider is registered automatically.

## Run the Install Command

```bash
php artisan escalated:install
```

This publishes:
- Configuration file to `config/escalated.php`
- Database migrations
- Vue page components (customer-facing)

### Publishing Admin Assets

To also install agent and admin UI:

```bash
php artisan vendor:publish --tag=escalated-admin-assets
```

## Run Migrations

```bash
php artisan migrate
```

## Implement the Ticketable Interface

The install command will offer to automatically add the `Ticketable` interface and `HasTickets` trait to your User model. If you skipped this step or use a custom user model, add them manually:

```php
use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;

class User extends Authenticatable implements Ticketable
{
    use HasTickets;

    // Your existing model code...
}
```

Without it, staff actions (replying, assigning, changing status) stop with
`Escalated\Laravel\Exceptions\UserNotTicketableException`, which names the
model and this contract. `escalated:install` ends with a setup check that
reports a user model without `Ticketable` and staff gates that are not
defined.

## Define Authorization Gates

In `App\Providers\AppServiceProvider::boot()` for Laravel 12+, or `App\Providers\AuthServiceProvider::boot()` for Laravel 11 and earlier:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('escalated-admin', function ($user) {
    return $user->is_admin; // Your admin check
});

Gate::define('escalated-agent', function ($user) {
    return $user->is_agent; // Your agent check
});
```

## Required: schedule the recurring jobs

`escalated:install` sets `ESCALATED_SCHEDULE_AUTO_REGISTER=true` in `.env`
(unless it is already there), so Escalated adds its recurring commands to the
Laravel scheduler. Make sure cron runs the scheduler every minute:

```
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

Without it, SLA checks, escalation rules, automations and snooze wake-ups never
run. See [the command list](operations.md#required-schedule-the-recurring-jobs).

## Update Config

Edit `config/escalated.php` to set your user model:

```php
'user_model' => App\Models\User::class,
```

## Visit the Support Portal

Navigate to `/support` to see the customer ticket portal.

- `/support` — Customer ticket list
- `/support/agent` — Agent dashboard
- `/support/admin/reports` — Admin reports
