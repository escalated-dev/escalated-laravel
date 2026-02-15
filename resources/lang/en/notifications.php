<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] New Ticket: :subject',
        'line1' => 'A new support ticket has been created.',
        'subject_line' => '**Subject:** :subject',
        'priority_line' => '**Priority:** :priority',
        'action' => 'View Ticket',
        'closing' => 'Thank you for using our support system.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket Assigned to You',
        'line1' => 'A ticket has been assigned to you.',
        'subject_line' => '**Subject:** :subject',
        'priority_line' => '**Priority:** :priority',
        'action' => 'View Ticket',
        'closing' => 'Please review and respond at your earliest convenience.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'A new reply has been added to your ticket.',
        'action' => 'View Ticket',
        'closing' => 'Thank you for using our support system.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket Resolved',
        'line1' => 'Your support ticket has been resolved.',
        'subject_line' => '**Subject:** :subject',
        'reopen_line' => 'If you need further assistance, you can reopen this ticket.',
        'action' => 'View Ticket',
        'closing' => 'Thank you for using our support system.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Status Updated: :status',
        'line1' => 'The status of your ticket has been updated.',
        'from_line' => '**From:** :status',
        'to_line' => '**To:** :status',
        'action' => 'View Ticket',
        'closing' => 'Thank you for using our support system.',
    ],

    'sla_breach' => [
        'subject' => '[SLA Breach] [:reference] :type SLA Breached',
        'type_first_response' => 'First Response',
        'type_resolution' => 'Resolution',
        'line1' => 'An SLA has been breached on ticket :reference.',
        'type_line' => '**Type:** :type SLA',
        'subject_line' => '**Subject:** :subject',
        'priority_line' => '**Priority:** :priority',
        'action' => 'View Ticket',
        'closing' => 'Immediate attention is required.',
    ],

    'ticket_escalated' => [
        'subject' => '[Escalated] [:reference] :subject',
        'line1' => 'A ticket has been escalated.',
        'subject_line' => '**Subject:** :subject',
        'priority_line' => '**Priority:** :priority',
        'reason_line' => '**Reason:** :reason',
        'action' => 'View Ticket',
        'closing' => 'Immediate attention is required.',
    ],

];
