<?php

return [

    'ticket' => [
        'reply_sent' => 'Reply sent.',
        'note_added' => 'Note added.',
        'assigned' => 'Ticket assigned.',
        'status_updated' => 'Status updated.',
        'priority_updated' => 'Priority updated.',
        'tags_updated' => 'Tags updated.',
        'department_updated' => 'Department updated.',
        'macro_applied' => 'Macro ":name" applied.',
        'following' => 'Following ticket.',
        'unfollowed' => 'Unfollowed ticket.',
        'only_internal_notes_pinned' => 'Only internal notes can be pinned.',
        'updated' => 'Ticket updated.',
        'created' => 'Ticket created successfully.',
        'closed' => 'Ticket closed.',
        'reopened' => 'Ticket reopened.',
        'customers_cannot_close' => 'Customers cannot close tickets.',
    ],

    'guest' => [
        'created' => 'Ticket created. Save this link to check your ticket status.',
        'ticket_closed' => 'This ticket is closed.',
    ],

    'bulk' => [
        'updated' => ':count ticket(s) updated.',
    ],

    'rating' => [
        'only_resolved_closed' => 'You can only rate resolved or closed tickets.',
        'already_rated' => 'This ticket has already been rated.',
        'thanks' => 'Thank you for your feedback!',
    ],

    'canned_response' => [
        'created' => 'Canned response created.',
        'updated' => 'Canned response updated.',
        'deleted' => 'Canned response deleted.',
    ],

    'department' => [
        'created' => 'Department created.',
        'updated' => 'Department updated.',
        'deleted' => 'Department deleted.',
    ],

    'tag' => [
        'created' => 'Tag created.',
        'updated' => 'Tag updated.',
        'deleted' => 'Tag deleted.',
    ],

    'macro' => [
        'created' => 'Macro created.',
        'updated' => 'Macro updated.',
        'deleted' => 'Macro deleted.',
    ],

    'escalation_rule' => [
        'created' => 'Rule created.',
        'updated' => 'Rule updated.',
        'deleted' => 'Rule deleted.',
    ],

    'sla_policy' => [
        'created' => 'SLA Policy created.',
        'updated' => 'SLA Policy updated.',
        'deleted' => 'SLA Policy deleted.',
    ],

    'settings' => [
        'updated' => 'Settings updated.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin uploaded successfully. You can now activate it.',
        'upload_failed' => 'Failed to upload plugin: :error',
        'activated' => 'Plugin activated successfully.',
        'activate_failed' => 'Failed to activate plugin: :error',
        'deactivated' => 'Plugin deactivated successfully.',
        'deactivate_failed' => 'Failed to deactivate plugin: :error',
        'composer_delete' => 'Composer plugins cannot be deleted. Remove the package via Composer instead.',
        'deleted' => 'Plugin deleted successfully.',
        'delete_failed' => 'Failed to delete plugin: :error',
    ],

    'middleware' => [
        'not_admin' => 'You are not authorized as a support administrator.',
        'not_agent' => 'You are not authorized as a support agent.',
    ],

    'inbound_email' => [
        'disabled' => 'Inbound email is disabled.',
        'unknown_adapter' => 'Unknown adapter.',
        'invalid_signature' => 'Invalid signature.',
        'processing_failed' => 'Processing failed.',
    ],

];
