<?php

return [

    'status' => [
        'open' => 'Offen',
        'in_progress' => 'In Bearbeitung',
        'waiting_on_customer' => 'Warten auf Kunde',
        'waiting_on_agent' => 'Warten auf Mitarbeiter',
        'escalated' => 'Eskaliert',
        'resolved' => 'Gelöst',
        'closed' => 'Geschlossen',
        'reopened' => 'Wiedereröffnet',
    ],

    'priority' => [
        'low' => 'Niedrig',
        'medium' => 'Mittel',
        'high' => 'Hoch',
        'urgent' => 'Dringend',
        'critical' => 'Kritisch',
    ],

    'activity_type' => [
        'status_changed' => 'Status geändert',
        'assigned' => 'Zugewiesen',
        'unassigned' => 'Zuweisung aufgehoben',
        'priority_changed' => 'Priorität geändert',
        'tag_added' => 'Tag hinzugefügt',
        'tag_removed' => 'Tag entfernt',
        'escalated' => 'Eskaliert',
        'sla_breached' => 'SLA verletzt',
        'replied' => 'Beantwortet',
        'note_added' => 'Notiz hinzugefügt',
        'department_changed' => 'Abteilung geändert',
        'reopened' => 'Wiedereröffnet',
        'resolved' => 'Gelöst',
        'closed' => 'Geschlossen',
        'ticket_split' => 'Ticket aufgeteilt',
    ],

];
