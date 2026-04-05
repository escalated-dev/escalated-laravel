<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hosting Mode
    |--------------------------------------------------------------------------
    |
    | Determines how ticket data is stored and synced.
    | - "self-hosted": All data in local DB. No external calls.
    | - "synced": Local DB + events synced to cloud.escalated.dev
    | - "cloud": All CRUD proxied to cloud.escalated.dev
    |
    */
    'mode' => env('ESCALATED_MODE', 'self-hosted'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => env('ESCALATED_USER_MODEL', 'App\\Models\\User'),
    'user_display_column' => env('ESCALATED_USER_DISPLAY_COLUMN', 'name'),

    /*
    |--------------------------------------------------------------------------
    | Hosted / Cloud Configuration
    |--------------------------------------------------------------------------
    */
    'hosted' => [
        'api_url' => env('ESCALATED_API_URL', 'https://cloud.escalated.dev/api/v1'),
        'api_key' => env('ESCALATED_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'support',
        'middleware' => ['web', 'auth'],
        'admin_middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Layer
    |--------------------------------------------------------------------------
    |
    | Controls whether the built-in Inertia UI is enabled. When disabled,
    | only core functionality is available: API routes, commands, events,
    | migrations, and the plugin runtime. This lets teams use a custom
    | frontend (Blade, Livewire, etc.) while keeping the ticketing backend.
    |
    */
    'ui' => [
        'enabled' => env('ESCALATED_UI_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    */
    'table_prefix' => 'escalated_',

    /*
    |--------------------------------------------------------------------------
    | Tickets
    |--------------------------------------------------------------------------
    */
    'tickets' => [
        'allow_customer_close' => true,
        'auto_close_resolved_after_days' => 7,
        'max_attachments_per_reply' => 5,
        'max_attachment_size_kb' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Priorities
    |--------------------------------------------------------------------------
    */
    'priorities' => ['low', 'medium', 'high', 'urgent', 'critical'],
    'default_priority' => 'medium',

    /*
    |--------------------------------------------------------------------------
    | Statuses & Transitions
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'open', 'in_progress', 'waiting_on_customer', 'waiting_on_agent',
        'escalated', 'resolved', 'closed', 'reopened',
    ],

    'transitions' => [
        'open' => ['in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
        'in_progress' => ['waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
        'waiting_on_customer' => ['open', 'in_progress', 'resolved', 'closed'],
        'waiting_on_agent' => ['open', 'in_progress', 'escalated', 'resolved', 'closed'],
        'escalated' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['reopened', 'closed'],
        'closed' => ['reopened'],
        'reopened' => ['in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA
    |--------------------------------------------------------------------------
    */
    'sla' => [
        'enabled' => true,
        'business_hours_only' => false,
        'business_hours' => [
            'start' => '09:00',
            'end' => '17:00',
            'timezone' => 'UTC',
            'days' => [1, 2, 3, 4, 5], // Monday through Friday
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'channels' => ['mail', 'database'],
        'webhook_url' => env('ESCALATED_WEBHOOK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage (Attachments)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => 'public',
        'path' => 'escalated/attachments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */
    'authorization' => [
        'admin_gate' => 'escalated-admin',
        'agent_gate' => 'escalated-agent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    */
    'scheduling' => [
        'auto_register' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */
    'activity_log' => [
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Configure the WordPress-style plugin/extension system. Plugins allow
    | third-party developers to extend Escalated with custom functionality.
    |
    */
    'plugins' => [
        'enabled' => env('ESCALATED_PLUGINS_ENABLED', true),
        'path' => app_path('Plugins/Escalated'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound Email
    |--------------------------------------------------------------------------
    |
    | Configure inbound email processing to create and reply to tickets via
    | email. Supports Mailgun, Postmark, SES webhooks, and IMAP polling.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    |
    | Enable the REST API for external integrations (desktop app, mobile, etc.).
    | Tokens are managed via the admin panel.
    |
    */
    'api' => [
        'enabled' => env('ESCALATED_API_ENABLED', false),
        'rate_limit' => env('ESCALATED_API_RATE_LIMIT', 60),
        'token_expiry_days' => null,
        'prefix' => 'support/api/v1',
    ],

    'inbound_email' => [
        'enabled' => env('ESCALATED_INBOUND_EMAIL', false),
        'adapter' => env('ESCALATED_INBOUND_ADAPTER', 'mailgun'),
        'address' => env('ESCALATED_INBOUND_ADDRESS', 'support@example.com'),

        'mailgun' => [
            'signing_key' => env('ESCALATED_MAILGUN_SIGNING_KEY'),
        ],

        'postmark' => [
            'token' => env('ESCALATED_POSTMARK_INBOUND_TOKEN'),
        ],

        'ses' => [
            'region' => env('ESCALATED_SES_REGION', 'us-east-1'),
            'topic_arn' => env('ESCALATED_SES_TOPIC_ARN'),
        ],

        'imap' => [
            'host' => env('ESCALATED_IMAP_HOST'),
            'port' => env('ESCALATED_IMAP_PORT', 993),
            'encryption' => env('ESCALATED_IMAP_ENCRYPTION', 'ssl'),
            'username' => env('ESCALATED_IMAP_USERNAME'),
            'password' => env('ESCALATED_IMAP_PASSWORD'),
            'mailbox' => env('ESCALATED_IMAP_MAILBOX', 'INBOX'),
        ],
    ],

];
