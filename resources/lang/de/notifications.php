<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Neues Ticket: :subject',
        'line1' => 'Ein neues Support-Ticket wurde erstellt.',
        'subject_line' => '**Betreff:** :subject',
        'priority_line' => '**Priorität:** :priority',
        'action' => 'Ticket ansehen',
        'closing' => 'Vielen Dank, dass Sie unser Support-System nutzen.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket Ihnen zugewiesen',
        'line1' => 'Ihnen wurde ein Ticket zugewiesen.',
        'subject_line' => '**Betreff:** :subject',
        'priority_line' => '**Priorität:** :priority',
        'action' => 'Ticket ansehen',
        'closing' => 'Bitte überprüfen und antworten Sie so bald wie möglich.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'Eine neue Antwort wurde zu Ihrem Ticket hinzugefügt.',
        'action' => 'Ticket ansehen',
        'closing' => 'Vielen Dank, dass Sie unser Support-System nutzen.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket gelöst',
        'line1' => 'Ihr Support-Ticket wurde gelöst.',
        'subject_line' => '**Betreff:** :subject',
        'reopen_line' => 'Wenn Sie weitere Hilfe benötigen, können Sie dieses Ticket erneut öffnen.',
        'action' => 'Ticket ansehen',
        'closing' => 'Vielen Dank, dass Sie unser Support-System nutzen.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Status aktualisiert: :status',
        'line1' => 'Der Status Ihres Tickets wurde aktualisiert.',
        'from_line' => '**Von:** :status',
        'to_line' => '**Zu:** :status',
        'action' => 'Ticket ansehen',
        'closing' => 'Vielen Dank, dass Sie unser Support-System nutzen.',
    ],

    'sla_breach' => [
        'subject' => '[SLA-Verletzung] [:reference] :type-SLA verletzt',
        'type_first_response' => 'Erste Antwort',
        'type_resolution' => 'Lösung',
        'line1' => 'Ein SLA wurde bei Ticket :reference verletzt.',
        'type_line' => '**Typ:** :type-SLA',
        'subject_line' => '**Betreff:** :subject',
        'priority_line' => '**Priorität:** :priority',
        'action' => 'Ticket ansehen',
        'closing' => 'Sofortige Aufmerksamkeit ist erforderlich.',
    ],

    'ticket_escalated' => [
        'subject' => '[Eskaliert] [:reference] :subject',
        'line1' => 'Ein Ticket wurde eskaliert.',
        'subject_line' => '**Betreff:** :subject',
        'priority_line' => '**Priorität:** :priority',
        'reason_line' => '**Grund:** :reason',
        'action' => 'Ticket ansehen',
        'closing' => 'Sofortige Aufmerksamkeit ist erforderlich.',
    ],

];
