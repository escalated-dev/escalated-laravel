# Building Plugins

Plugins extend Escalated with custom functionality using a WordPress-style hook system. Plugins can be distributed as ZIP files (uploaded via the admin panel) or as Composer packages.

## Plugin Structure

A minimal plugin needs two files:

```
my-plugin/
  plugin.json      # Manifest (required)
  Plugin.php       # Entry point (required)
```

### plugin.json

```json
{
    "name": "My Plugin",
    "slug": "my-plugin",
    "description": "A short description of what this plugin does.",
    "version": "1.0.0",
    "author": "Your Name",
    "author_url": "https://example.com",
    "requires": "1.0.0",
    "main_file": "Plugin.php"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `name` | Yes | Human-readable plugin name |
| `slug` | Yes | Unique identifier (lowercase, hyphens only) |
| `description` | No | Short description shown in the admin panel |
| `version` | Yes | Semver version string |
| `author` | No | Author name |
| `author_url` | No | Author website URL |
| `requires` | No | Minimum Escalated version required |
| `main_file` | No | Entry point filename (defaults to `Plugin.php`) |

### Plugin.php

The main file is loaded via `require_once` when the plugin is activated. Use it to register hooks:

```php
<?php

// Runs every time a ticket is created
escalated_add_action('escalated_ticket_created', function ($ticket) {
    // Send a Slack notification, create a Jira issue, etc.
    \Log::info("New ticket: {$ticket->reference}");
});

// Modify ticket data before it's saved
escalated_add_filter('escalated_ticket_data', function ($data) {
    $data['custom_field'] = 'value';
    return $data;
});
```

## Distribution Methods

### ZIP Upload (Local Plugins)

1. Create a ZIP file containing your plugin folder at the root:
   ```
   my-plugin.zip
     └── my-plugin/
           ├── plugin.json
           └── Plugin.php
   ```
2. Go to **Admin > Plugins** and upload the ZIP file.
3. Click **Inactive** to activate the plugin.

Uploaded plugins are stored in `app/Plugins/Escalated/`.

### Composer Package

Any Composer package that includes a `plugin.json` at its root is automatically detected:

```
composer require acme/escalated-billing
```

The package just needs a `plugin.json` alongside its `composer.json`:

```
vendor/acme/escalated-billing/
  composer.json
  plugin.json        # ← Escalated detects this
  Plugin.php
  src/
    ...
```

Composer plugins appear in the admin panel with a **composer** badge. They cannot be deleted from the UI — use `composer remove` instead.

**Composer plugin slugs** are derived from the package name: `acme/escalated-billing` becomes `acme--escalated-billing`.

## Hook API

### Action Hooks

Actions let you run code when something happens. They don't return a value.

```php
// Register an action
escalated_add_action(string $tag, callable $callback, int $priority = 10): void

// Fire an action (used internally by Escalated)
escalated_do_action(string $tag, ...$args): void

// Check if an action has callbacks
escalated_has_action(string $tag): bool

// Remove an action
escalated_remove_action(string $tag, ?callable $callback = null): void
```

### Filter Hooks

Filters let you modify data as it passes through the system. Callbacks receive the current value and must return the modified value.

```php
// Register a filter
escalated_add_filter(string $tag, callable $callback, int $priority = 10): void

// Apply filters (used internally by Escalated)
escalated_apply_filters(string $tag, mixed $value, ...$args): mixed

// Check if a filter has callbacks
escalated_has_filter(string $tag): bool

// Remove a filter
escalated_remove_filter(string $tag, ?callable $callback = null): void
```

### Priority

Lower numbers run first. The default priority is `10`. Use lower values (e.g. `5`) to run before other callbacks, or higher values (e.g. `20`) to run after.

```php
// This runs first
escalated_add_action('escalated_ticket_created', function ($ticket) {
    // early processing
}, 5);

// This runs second
escalated_add_action('escalated_ticket_created', function ($ticket) {
    // later processing
}, 20);
```

## Available Hooks

### Plugin Lifecycle

| Hook | Args | When |
|------|------|------|
| `escalated_plugin_loaded` | `$slug, $manifest` | Plugin file is loaded |
| `escalated_plugin_activated` | `$slug` | Plugin is activated |
| `escalated_plugin_activated_{slug}` | — | Your specific plugin is activated |
| `escalated_plugin_deactivated` | `$slug` | Plugin is deactivated |
| `escalated_plugin_deactivated_{slug}` | — | Your specific plugin is deactivated |
| `escalated_plugin_uninstalling` | `$slug` | Plugin is about to be deleted |
| `escalated_plugin_uninstalling_{slug}` | — | Your specific plugin is about to be deleted |

Use the `{slug}` variants to run code only for your own plugin:

```php
escalated_add_action('escalated_plugin_activated_my-plugin', function () {
    // Run migrations, seed data, etc.
});

escalated_add_action('escalated_plugin_uninstalling_my-plugin', function () {
    // Clean up database tables, cached files, etc.
});
```

## UI Helpers

Plugins can register UI elements that appear in the Escalated interface.

### Menu Items

```php
escalated_register_menu_item([
    'label' => 'Billing',
    'url' => '/support/admin/billing',
    'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5...',  // Heroicon SVG path
    'section' => 'admin',  // 'admin', 'agent', or 'customer'
    'priority' => 50,
]);
```

### Custom Pages

```php
escalated_register_page(
    'admin/billing',                    // Route path
    'Escalated/Admin/Billing',          // Inertia component
    ['middleware' => ['auth']]           // Options
);
```

### Dashboard Widgets

```php
escalated_register_dashboard_widget([
    'id' => 'billing-summary',
    'label' => 'Billing Summary',
    'component' => 'BillingSummaryWidget',
    'section' => 'agent',
    'priority' => 10,
]);
```

### Page Components (Slots)

Inject components into existing pages:

```php
escalated_add_page_component(
    'ticket-detail',   // Page identifier
    'sidebar',         // Slot name
    [
        'component' => 'BillingInfo',
        'props' => ['show_total' => true],
        'priority' => 10,
    ]
);
```

## Full Example: Slack Notifier Plugin

```
slack-notifier/
  plugin.json
  Plugin.php
```

**plugin.json:**
```json
{
    "name": "Slack Notifier",
    "slug": "slack-notifier",
    "description": "Posts a message to Slack when a new ticket is created.",
    "version": "1.0.0",
    "author": "Acme Corp",
    "main_file": "Plugin.php"
}
```

**Plugin.php:**
```php
<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

escalated_add_action('escalated_plugin_activated_slack-notifier', function () {
    Log::info('Slack Notifier plugin activated');
});

escalated_add_action('escalated_ticket_created', function ($ticket) {
    $webhookUrl = config('services.slack.webhook_url');

    if (! $webhookUrl) {
        return;
    }

    Http::post($webhookUrl, [
        'text' => "New ticket *{$ticket->reference}*: {$ticket->subject}",
    ]);
});

escalated_add_action('escalated_plugin_uninstalling_slack-notifier', function () {
    Log::info('Slack Notifier plugin uninstalled');
});
```

## Full Example: Composer Package

A Composer-distributed plugin follows the same conventions. Your `composer.json` and `plugin.json` live side by side:

**composer.json:**
```json
{
    "name": "acme/escalated-billing",
    "description": "Billing integration for Escalated",
    "type": "library",
    "require": {
        "php": "^8.1"
    },
    "autoload": {
        "psr-4": {
            "Acme\\EscalatedBilling\\": "src/"
        }
    }
}
```

**plugin.json:**
```json
{
    "name": "Billing Integration",
    "slug": "acme--escalated-billing",
    "description": "Adds billing and invoicing to Escalated.",
    "version": "2.0.0",
    "author": "Acme Corp",
    "main_file": "Plugin.php"
}
```

**Plugin.php:**
```php
<?php

use Acme\EscalatedBilling\BillingService;

escalated_add_action('escalated_ticket_created', function ($ticket) {
    app(BillingService::class)->trackTicket($ticket);
});

escalated_register_menu_item([
    'label' => 'Billing',
    'url' => '/support/admin/billing',
    'icon' => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
    'section' => 'admin',
]);
```

Since Composer handles autoloading, your `Plugin.php` can use classes from `src/` without any manual `require` statements.

## Tips

- **Keep Plugin.php lightweight.** Register hooks and delegate to service classes.
- **Use activation hooks** to run migrations or seed data on first activation.
- **Use uninstall hooks** to clean up database tables when your plugin is removed.
- **Namespace your hooks** to avoid collisions: `escalated_myplugin_custom_action`.
- **Test locally** by placing your plugin folder in `app/Plugins/Escalated/` and activating it from the admin panel.
- **Composer plugins** benefit from PSR-4 autoloading, testing infrastructure, and version management via Packagist.
