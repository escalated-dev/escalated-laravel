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
    |
    | `user_display_column` is the column Escalated reads + searches to
    | show the requester's human-readable name. Defaults to `name`.
    |
    | If your `users` table uses split columns (e.g. `first_name` /
    | `last_name`) or any other schema without a single `name` column,
    | point this at whichever column holds the display text (e.g.
    | `'first_name'`). If the configured column doesn't exist on the
    | table at all, Escalated silently falls back to searching the
    | `email` column — so no code changes are required for hosts with
    | non-standard user schemas.
    |
    */
    'user_model' => env('ESCALATED_USER_MODEL', 'App\\Models\\User'),
    'user_display_column' => env('ESCALATED_USER_DISPLAY_COLUMN', 'name'),

    /*
    |--------------------------------------------------------------------------
    | User Key Type
    |--------------------------------------------------------------------------
    |
    | Column type for the user-referencing foreign keys Escalated creates
    | (ticket_followers.user_id, agent_profiles.user_id, tickets.assigned_to,
    | etc.). Defaults to 'auto', which reflects your user model's key type so
    | UUID/ULID/string-keyed user tables migrate cleanly with no edits. Set
    | explicitly to 'bigint', 'uuid', 'ulid', or 'string' to override.
    |
    | Note: this affects columns at migration time. Apps that already migrated
    | (e.g. as 'bigint') keep their existing columns; switching key types after
    | install requires a manual migration.
    |
    */
    'user_key_type' => env('ESCALATED_USER_KEY_TYPE', 'auto'),

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
    'table_prefix' => env('ESCALATED_TABLE_PREFIX', 'escalated_'),

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
    | Ticket Actions
    |--------------------------------------------------------------------------
    |
    | Register host-application actions that should appear as buttons on the
    | agent ticket screen. When clicked, Escalated dispatches the
    | TicketCustomActionTriggered event so the host app can handle the work in
    | a normal Laravel listener.
    |
    | Each action may be a class implementing TicketAction or an array with:
    | key, label, variant, confirmation, visible, enabled, and metadata.
    |
    */
    'ticket_actions' => [
        'actions' => [],
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
        'escalated', 'resolved', 'closed', 'reopened', 'live',
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
        'live' => ['open', 'resolved', 'closed'],
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
    | Email (outbound + inbound)
    |--------------------------------------------------------------------------
    |
    | `domain` is the right-hand side of RFC 5322 Message-IDs and
    | signed Reply-To addresses. Defaults to the host parsed from
    | APP_URL, then falls back to "escalated.dev".
    |
    | `inbound_secret` is the HMAC key used to sign the Reply-To local
    | part (reply+{id}.{hmac8}@domain). When empty, Reply-To is left
    | untouched — basic threading still works via Message-ID /
    | In-Reply-To, but inbound providers can't verify ticket identity.
    |
    */
    'email' => [
        'domain' => env('ESCALATED_EMAIL_DOMAIN'),
        'inbound_secret' => env('ESCALATED_EMAIL_INBOUND_SECRET', ''),
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
        'mobile_prefix' => 'support/api/v1/mobile',
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting / Real-time
    |--------------------------------------------------------------------------
    |
    | Enable WebSocket broadcasting for core ticket events. Requires a
    | broadcasting driver (Pusher, Ably, Reverb, Soketi, etc.) configured
    | in the host application. When disabled (default), the frontend falls
    | back to polling automatically.
    |
    */
    'broadcasting' => [
        'enabled' => env('ESCALATED_BROADCASTING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Chat
    |--------------------------------------------------------------------------
    |
    | Configure the live chat / widget chat feature. Chat sessions are
    | tickets with status "live" and channel "chat" that stream messages
    | in real-time via WebSocket.
    |
    */
    'chat' => [
        'enabled' => env('ESCALATED_CHAT_ENABLED', false),
        'sound_enabled' => true,
        'auto_close_idle_minutes' => 30,
        'abandoned_timeout_minutes' => 10,
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

    /*
    |--------------------------------------------------------------------------
    | Ticket subjects
    |--------------------------------------------------------------------------
    |
    | Host-app models a ticket can be *about* (a Project, Customer, asset, …),
    | distinct from the requester. Attached models should implement
    | Escalated\Laravel\Contracts\TicketSubject (or use the
    | PresentsAsTicketSubject trait) so they render in the ticket UI.
    |
    | `types` is the allowlist of morph types (class names or morph-map
    | aliases) the agent API is permitted to attach — this prevents arbitrary
    | class resolution from request input. Leave empty to disable attaching via
    | the API; programmatic $ticket->attachSubject($model) still works.
    |
    */
    'ticket_subjects' => [
        'types' => [
            // \App\Models\Project::class,
            // 'project' => \App\Models\Project::class,
        ],
    ],

];
