<?php

namespace Escalated\Laravel\Services;

/**
 * Central registry of all available hooks and filters in the Escalated package.
 * This class serves as documentation - plugins can reference this to know what's available.
 */
class HookRegistry
{
    /**
     * Get all available action hooks.
     */
    public static function getActions(): array
    {
        return [
            // ========================================
            // PLUGIN LIFECYCLE
            // ========================================
            'escalated_plugin_loaded' => [
                'description' => 'Fired when a plugin is loaded',
                'parameters' => ['$slug', '$manifest'],
                'example' => "escalated_add_action('escalated_plugin_loaded', function(\$slug, \$manifest) { /* ... */ });",
            ],
            'escalated_plugin_activated' => [
                'description' => 'Fired when any plugin is activated',
                'parameters' => ['$slug'],
                'example' => "escalated_add_action('escalated_plugin_activated', function(\$slug) { /* ... */ });",
            ],
            'escalated_plugin_activated_{slug}' => [
                'description' => 'Fired when a specific plugin is activated (replace {slug} with your plugin name)',
                'parameters' => [],
                'example' => "escalated_add_action('escalated_plugin_activated_my-plugin', function() { /* ... */ });",
            ],
            'escalated_plugin_deactivated' => [
                'description' => 'Fired when any plugin is deactivated',
                'parameters' => ['$slug'],
                'example' => "escalated_add_action('escalated_plugin_deactivated', function(\$slug) { /* ... */ });",
            ],
            'escalated_plugin_deactivated_{slug}' => [
                'description' => 'Fired when a specific plugin is deactivated',
                'parameters' => [],
                'example' => "escalated_add_action('escalated_plugin_deactivated_my-plugin', function() { /* ... */ });",
            ],
            'escalated_plugin_uninstalling' => [
                'description' => 'Fired before any plugin is deleted',
                'parameters' => ['$slug'],
                'example' => "escalated_add_action('escalated_plugin_uninstalling', function(\$slug) { /* ... */ });",
            ],
            'escalated_plugin_uninstalling_{slug}' => [
                'description' => 'Fired before a specific plugin is deleted',
                'parameters' => [],
                'example' => "escalated_add_action('escalated_plugin_uninstalling_my-plugin', function() { /* ... */ });",
            ],

            // ========================================
            // TICKET HOOKS
            // ========================================
            'escalated_ticket_created' => [
                'description' => 'Fired after a ticket is created',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_created', function(\$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_updated' => [
                'description' => 'Fired after a ticket is updated',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_updated', function(\$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_deleted' => [
                'description' => 'Fired after a ticket is deleted',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_deleted', function(\$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_status_changed' => [
                'description' => 'Fired when a ticket status changes',
                'parameters' => ['$ticket', '$old_status', '$new_status', '$user'],
                'example' => "escalated_add_action('escalated_ticket_status_changed', function(\$ticket, \$old, \$new, \$user) { /* ... */ });",
            ],
            'escalated_ticket_assigned' => [
                'description' => 'Fired when a ticket is assigned to an agent',
                'parameters' => ['$ticket', '$agent', '$user'],
                'example' => "escalated_add_action('escalated_ticket_assigned', function(\$ticket, \$agent, \$user) { /* ... */ });",
            ],
            'escalated_ticket_unassigned' => [
                'description' => 'Fired when a ticket is unassigned',
                'parameters' => ['$ticket', '$previous_agent', '$user'],
                'example' => "escalated_add_action('escalated_ticket_unassigned', function(\$ticket, \$previousAgent, \$user) { /* ... */ });",
            ],
            'escalated_ticket_replied' => [
                'description' => 'Fired when a reply is added to a ticket',
                'parameters' => ['$reply', '$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_replied', function(\$reply, \$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_escalated' => [
                'description' => 'Fired when a ticket is escalated',
                'parameters' => ['$ticket', '$rule', '$user'],
                'example' => "escalated_add_action('escalated_ticket_escalated', function(\$ticket, \$rule, \$user) { /* ... */ });",
            ],
            'escalated_ticket_resolved' => [
                'description' => 'Fired when a ticket is resolved',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_resolved', function(\$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_closed' => [
                'description' => 'Fired when a ticket is closed',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_closed', function(\$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_reopened' => [
                'description' => 'Fired when a ticket is reopened',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_action('escalated_ticket_reopened', function(\$ticket, \$user) { /* ... */ });",
            ],
            'escalated_ticket_priority_changed' => [
                'description' => 'Fired when ticket priority changes',
                'parameters' => ['$ticket', '$old_priority', '$new_priority', '$user'],
                'example' => "escalated_add_action('escalated_ticket_priority_changed', function(\$ticket, \$old, \$new, \$user) { /* ... */ });",
            ],
            'escalated_internal_note_added' => [
                'description' => 'Fired when an internal note is added to a ticket',
                'parameters' => ['$reply', '$ticket', '$user'],
                'example' => "escalated_add_action('escalated_internal_note_added', function(\$reply, \$ticket, \$user) { /* ... */ });",
            ],

            // ========================================
            // DEPARTMENT HOOKS
            // ========================================
            'escalated_department_created' => [
                'description' => 'Fired after a department is created',
                'parameters' => ['$department', '$user'],
                'example' => "escalated_add_action('escalated_department_created', function(\$department, \$user) { /* ... */ });",
            ],
            'escalated_department_updated' => [
                'description' => 'Fired after a department is updated',
                'parameters' => ['$department', '$user'],
                'example' => "escalated_add_action('escalated_department_updated', function(\$department, \$user) { /* ... */ });",
            ],
            'escalated_department_deleted' => [
                'description' => 'Fired after a department is deleted',
                'parameters' => ['$department', '$user'],
                'example' => "escalated_add_action('escalated_department_deleted', function(\$department, \$user) { /* ... */ });",
            ],
            'escalated_ticket_department_changed' => [
                'description' => 'Fired when a ticket is moved to a different department',
                'parameters' => ['$ticket', '$old_department', '$new_department', '$user'],
                'example' => "escalated_add_action('escalated_ticket_department_changed', function(\$ticket, \$old, \$new, \$user) { /* ... */ });",
            ],

            // ========================================
            // TAG HOOKS
            // ========================================
            'escalated_tag_created' => [
                'description' => 'Fired after a tag is created',
                'parameters' => ['$tag', '$user'],
                'example' => "escalated_add_action('escalated_tag_created', function(\$tag, \$user) { /* ... */ });",
            ],
            'escalated_tag_updated' => [
                'description' => 'Fired after a tag is updated',
                'parameters' => ['$tag', '$user'],
                'example' => "escalated_add_action('escalated_tag_updated', function(\$tag, \$user) { /* ... */ });",
            ],
            'escalated_tag_deleted' => [
                'description' => 'Fired after a tag is deleted',
                'parameters' => ['$tag', '$user'],
                'example' => "escalated_add_action('escalated_tag_deleted', function(\$tag, \$user) { /* ... */ });",
            ],
            'escalated_tag_added_to_ticket' => [
                'description' => 'Fired when a tag is added to a ticket',
                'parameters' => ['$tag', '$ticket', '$user'],
                'example' => "escalated_add_action('escalated_tag_added_to_ticket', function(\$tag, \$ticket, \$user) { /* ... */ });",
            ],
            'escalated_tag_removed_from_ticket' => [
                'description' => 'Fired when a tag is removed from a ticket',
                'parameters' => ['$tag', '$ticket', '$user'],
                'example' => "escalated_add_action('escalated_tag_removed_from_ticket', function(\$tag, \$ticket, \$user) { /* ... */ });",
            ],

            // ========================================
            // SLA HOOKS
            // ========================================
            'escalated_sla_policy_created' => [
                'description' => 'Fired after an SLA policy is created',
                'parameters' => ['$policy', '$user'],
                'example' => "escalated_add_action('escalated_sla_policy_created', function(\$policy, \$user) { /* ... */ });",
            ],
            'escalated_sla_policy_updated' => [
                'description' => 'Fired after an SLA policy is updated',
                'parameters' => ['$policy', '$user'],
                'example' => "escalated_add_action('escalated_sla_policy_updated', function(\$policy, \$user) { /* ... */ });",
            ],
            'escalated_sla_breached' => [
                'description' => 'Fired when an SLA is breached',
                'parameters' => ['$ticket', '$breach_type'],
                'example' => "escalated_add_action('escalated_sla_breached', function(\$ticket, \$breachType) { /* ... */ });",
            ],
            'escalated_sla_warning' => [
                'description' => 'Fired when an SLA is approaching its deadline',
                'parameters' => ['$ticket', '$warning_type'],
                'example' => "escalated_add_action('escalated_sla_warning', function(\$ticket, \$warningType) { /* ... */ });",
            ],

            // ========================================
            // DASHBOARD HOOKS
            // ========================================
            'escalated_dashboard_viewed' => [
                'description' => 'Fired when the agent dashboard is viewed',
                'parameters' => ['$user'],
                'example' => "escalated_add_action('escalated_dashboard_viewed', function(\$user) { /* ... */ });",
            ],
            'escalated_admin_dashboard_viewed' => [
                'description' => 'Fired when the admin dashboard is viewed',
                'parameters' => ['$user'],
                'example' => "escalated_add_action('escalated_admin_dashboard_viewed', function(\$user) { /* ... */ });",
            ],

            // ========================================
            // SATISFACTION HOOKS
            // ========================================
            'escalated_satisfaction_rating_submitted' => [
                'description' => 'Fired when a customer submits a satisfaction rating',
                'parameters' => ['$rating', '$ticket'],
                'example' => "escalated_add_action('escalated_satisfaction_rating_submitted', function(\$rating, \$ticket) { /* ... */ });",
            ],
        ];
    }

    /**
     * Get all available filter hooks.
     */
    public static function getFilters(): array
    {
        return [
            // ========================================
            // TICKET FILTERS
            // ========================================
            'escalated_ticket_display_subject' => [
                'description' => 'Modify ticket subject before display',
                'parameters' => ['$subject', '$ticket'],
                'example' => "escalated_add_filter('escalated_ticket_display_subject', function(\$subject, \$ticket) { return strtoupper(\$subject); });",
            ],
            'escalated_ticket_list_query' => [
                'description' => 'Modify the ticket listing database query',
                'parameters' => ['$query', '$request'],
                'example' => "escalated_add_filter('escalated_ticket_list_query', function(\$query, \$request) { return \$query->where('priority', 'urgent'); });",
            ],
            'escalated_ticket_list_data' => [
                'description' => 'Modify the ticket collection before rendering list',
                'parameters' => ['$tickets', '$request'],
                'example' => "escalated_add_filter('escalated_ticket_list_data', function(\$tickets, \$request) { /* modify */ return \$tickets; });",
            ],
            'escalated_ticket_show_data' => [
                'description' => 'Modify ticket data before displaying detail page',
                'parameters' => ['$ticket', '$user'],
                'example' => "escalated_add_filter('escalated_ticket_show_data', function(\$ticket, \$user) { /* modify */ return \$ticket; });",
            ],
            'escalated_ticket_store_data' => [
                'description' => 'Modify validated data before creating ticket',
                'parameters' => ['$validated_data', '$request'],
                'example' => "escalated_add_filter('escalated_ticket_store_data', function(\$data, \$request) { \$data['custom'] = 'value'; return \$data; });",
            ],
            'escalated_ticket_reply_data' => [
                'description' => 'Modify reply data before saving',
                'parameters' => ['$data', '$ticket', '$request'],
                'example' => "escalated_add_filter('escalated_ticket_reply_data', function(\$data, \$ticket, \$request) { return \$data; });",
            ],
            'escalated_ticket_status_options' => [
                'description' => 'Modify available ticket status options',
                'parameters' => ['$statuses'],
                'example' => "escalated_add_filter('escalated_ticket_status_options', function(\$statuses) { \$statuses[] = 'custom_status'; return \$statuses; });",
            ],
            'escalated_ticket_priority_options' => [
                'description' => 'Modify available ticket priority options',
                'parameters' => ['$priorities'],
                'example' => "escalated_add_filter('escalated_ticket_priority_options', function(\$priorities) { return \$priorities; });",
            ],

            // ========================================
            // DASHBOARD FILTERS
            // ========================================
            'escalated_dashboard_stats_data' => [
                'description' => 'Modify dashboard statistics data',
                'parameters' => ['$stats', '$user'],
                'example' => "escalated_add_filter('escalated_dashboard_stats_data', function(\$stats, \$user) { \$stats['custom_metric'] = 100; return \$stats; });",
            ],
            'escalated_dashboard_page_data' => [
                'description' => 'Modify all data passed to dashboard page',
                'parameters' => ['$data', '$user'],
                'example' => "escalated_add_filter('escalated_dashboard_page_data', function(\$data, \$user) { return \$data; });",
            ],

            // ========================================
            // UI FILTERS
            // ========================================
            'escalated_navigation_menu' => [
                'description' => 'Add or modify navigation menu items',
                'parameters' => ['$menu_items', '$user'],
                'example' => "escalated_add_filter('escalated_navigation_menu', function(\$items, \$user) { \$items[] = ['name' => 'Custom', 'route' => 'custom.route']; return \$items; });",
            ],
            'escalated_sidebar_menu' => [
                'description' => 'Add or modify sidebar menu items',
                'parameters' => ['$menu_items', '$user'],
                'example' => "escalated_add_filter('escalated_sidebar_menu', function(\$items, \$user) { return \$items; });",
            ],
            'escalated_admin_navigation_menu' => [
                'description' => 'Add or modify admin navigation menu items',
                'parameters' => ['$menu_items', '$user'],
                'example' => "escalated_add_filter('escalated_admin_navigation_menu', function(\$items, \$user) { return \$items; });",
            ],

            // ========================================
            // REPORT FILTERS
            // ========================================
            'escalated_report_data' => [
                'description' => 'Modify report data before display',
                'parameters' => ['$data', '$report_type', '$user'],
                'example' => "escalated_add_filter('escalated_report_data', function(\$data, \$type, \$user) { return \$data; });",
            ],

            // ========================================
            // NOTIFICATION FILTERS
            // ========================================
            'escalated_notification_channels' => [
                'description' => 'Modify notification channels for a given event',
                'parameters' => ['$channels', '$notification', '$notifiable'],
                'example' => "escalated_add_filter('escalated_notification_channels', function(\$channels, \$notification, \$notifiable) { \$channels[] = 'slack'; return \$channels; });",
            ],
            'escalated_notification_data' => [
                'description' => 'Modify notification data before sending',
                'parameters' => ['$data', '$notification'],
                'example' => "escalated_add_filter('escalated_notification_data', function(\$data, \$notification) { return \$data; });",
            ],

            // ========================================
            // CANNED RESPONSE FILTERS
            // ========================================
            'escalated_canned_response_content' => [
                'description' => 'Modify canned response content before applying',
                'parameters' => ['$content', '$canned_response', '$ticket'],
                'example' => "escalated_add_filter('escalated_canned_response_content', function(\$content, \$response, \$ticket) { return str_replace('{{name}}', \$ticket->requester_name, \$content); });",
            ],
        ];
    }

    /**
     * Get all hooks (both actions and filters).
     */
    public static function getAllHooks(): array
    {
        return [
            'actions' => self::getActions(),
            'filters' => self::getFilters(),
        ];
    }
}
