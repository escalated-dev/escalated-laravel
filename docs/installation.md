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

## Define Authorization Gates

In your `AppServiceProvider` or `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('escalated-admin', function ($user) {
    return $user->is_admin; // Your admin check
});

Gate::define('escalated-agent', function ($user) {
    return $user->is_agent || $user->is_admin; // Your agent check
});
```

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
