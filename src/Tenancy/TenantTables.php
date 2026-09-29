<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Escalated;

/** Explicit package-owned tables; never infer ownership from a host table prefix. */
class TenantTables
{
    // Installation administration, immutable permission vocabulary and host
    // identity security. None carries merchant correspondence or credentials.
    public const PLATFORM = ['plugins', 'permissions', 'two_factor', 'attachment_migration_locks'];

    // Frozen table set used by the initial tenant migration. Later tables own
    // their tenant column in their creation migration.
    public const INITIAL_NAMES = [
        'agent_capacity',
        'agent_profiles',
        'agent_skill',
        'api_tokens',
        'article_categories',
        'articles',
        'attachments',
        'attachment_migrations',
        'audit_logs',
        'automations',
        'business_schedules',
        'canned_responses',
        'chat_routing_rules',
        'chat_sessions',
        'contacts',
        'custom_field_values',
        'custom_fields',
        'custom_object_records',
        'custom_objects',
        'delayed_actions',
        'department_agent',
        'departments',
        'escalation_rules',
        'holidays',
        'import_jobs',
        'import_source_maps',
        'inbound_emails',
        'macros',
        'mentions',
        'newsletter_deliveries',
        'newsletter_list_members',
        'newsletter_lists',
        'newsletter_templates',
        'newsletters',
        'plugin_store',
        'replies',
        'role_permission',
        'role_user',
        'roles',
        'satisfaction_ratings',
        'saved_views',
        'settings',
        'side_conversation_replies',
        'side_conversations',
        'skills',
        'sla_policies',
        'tags',
        'ticket_activities',
        'ticket_followers',
        'ticket_links',
        'ticket_statuses',
        'ticket_subjects',
        'ticket_tag',
        'tickets',
        'webhook_deliveries',
        'webhooks',
        'workflow_logs',
        'workflows',
    ];

    public const NAMES = [...self::INITIAL_NAMES, 'guest_verifications'];

    public static function contains(string $table): bool
    {
        return in_array($table, array_map(Escalated::table(...), self::NAMES), true);
    }
}
