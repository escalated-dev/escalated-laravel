# Newsletters

## Newsletters (optional, disabled by default)

An admin-only broadcast feature for sending Markdown emails to contacts. Disabled by default — when off, no newsletter routes, controllers, or scheduler hooks register.

```env
ESCALATED_ENABLE_NEWSLETTERS=true
ESCALATED_NEWSLETTER_DEFAULT_FROM=hi@example.com
ESCALATED_NEWSLETTER_DEFAULT_THEME=default
```

Add a Laravel scheduler entry in `app/Console/Kernel.php`:

```php
$schedule->command('escalated:newsletters:dispatch')->everyMinute()->withoutOverlapping();
```

Then re-seed permissions to attach `newsletters.manage` and `newsletters.send` to the Admin role:

```bash
php artisan db:seed --class=Escalated\\Laravel\\Database\\Seeders\\PermissionSeeder
```

Custom themes live in `resources/views/vendor/escalated/newsletters/themes/<slug>.blade.php`.

ESP webhook endpoints for outbound events:

- `POST /escalated/webhooks/newsletter/postmark`
- `POST /escalated/webhooks/newsletter/mailgun`
- `POST /escalated/webhooks/newsletter/ses`
- `POST /escalated/webhooks/newsletter/sendgrid`
