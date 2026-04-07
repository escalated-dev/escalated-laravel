<?php

use Illuminate\Support\Facades\Log;

/**
 * Hello World Plugin for Escalated
 *
 * Demonstrates the plugin system by:
 * - Adding a dashboard widget
 * - Adding a ticket sidebar component
 * - Filtering ticket display subject
 * - Logging ticket creation events
 */

// Register a dashboard widget
escalated_register_dashboard_widget([
    'id' => 'hello-world-widget',
    'title' => 'Hello World',
    'component' => 'HelloWorldBanner',
    'data' => [
        'plugin' => 'hello-world',
        'message' => 'This widget was added by the Hello World plugin!',
    ],
    'position' => 50,
    'width' => 'full',
    'target' => 'agent',
]);

// Register a custom menu item
escalated_register_menu_item([
    'label' => 'Hello World',
    'route' => null,
    'url' => 'https://escalated.dev/plugins/hello-world',
    'icon' => 'hand-wave',
    'position' => 200,
    'target' => 'agent',
]);

// Add a component to the ticket detail sidebar
escalated_add_page_component('ticket.show', 'sidebar', [
    'component' => 'HelloWorldBanner',
    'data' => [
        'plugin' => 'hello-world',
        'message' => 'Hello from the ticket sidebar!',
    ],
    'position' => 100,
]);

// Filter: Append a greeting to ticket subjects
escalated_add_filter('escalated_ticket_display_subject', function (string $subject, $ticket) {
    // Example: Prefix VIP tickets
    return $subject;
}, 10);

// Action: Log when a ticket is created
escalated_add_action('escalated_ticket_created', function ($ticket, $user) {
    Log::info('Hello World Plugin: Ticket created', [
        'ticket_id' => $ticket->id ?? null,
        'user_id' => $user->id ?? null,
    ]);
}, 10);

// Action: Run code when this plugin is activated
escalated_add_action('escalated_plugin_activated_hello-world', function () {
    Log::info('Hello World Plugin: Activated!');
});

// Action: Run code when this plugin is deactivated
escalated_add_action('escalated_plugin_deactivated_hello-world', function () {
    Log::info('Hello World Plugin: Deactivated!');
});

// Action: Clean up when this plugin is being uninstalled
escalated_add_action('escalated_plugin_uninstalling_hello-world', function () {
    Log::info('Hello World Plugin: Uninstalling - cleaning up...');
});
