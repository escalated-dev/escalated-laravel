<?php

return [

    'status' => [
        'open' => 'Aperto',
        'in_progress' => 'In corso',
        'waiting_on_customer' => 'In attesa del cliente',
        'waiting_on_agent' => "In attesa dell'agente",
        'escalated' => 'Escalato',
        'resolved' => 'Risolto',
        'closed' => 'Chiuso',
        'snoozed' => 'Posticipato',
        'unsnoozed' => 'Riattivato',
        'reopened' => 'Riaperto',
        'live' => 'Live',
    ],

    'priority' => [
        'low' => 'Bassa',
        'medium' => 'Media',
        'high' => 'Alta',
        'urgent' => 'Urgente',
        'critical' => 'Critica',
    ],

    'activity_type' => [
        'status_changed' => 'Stato modificato',
        'assigned' => 'Assegnato',
        'unassigned' => 'Non assegnato',
        'priority_changed' => 'Priorità modificata',
        'tag_added' => 'Tag aggiunto',
        'tag_removed' => 'Tag rimosso',
        'escalated' => 'Escalato',
        'sla_breached' => 'SLA violato',
        'replied' => 'Risposto',
        'note_added' => 'Nota aggiunta',
        'department_changed' => 'Reparto modificato',
        'reopened' => 'Riaperto',
        'resolved' => 'Risolto',
        'closed' => 'Chiuso',
        'snoozed' => 'Posticipato',
        'unsnoozed' => 'Riattivato',
        'ticket_split' => 'Ticket diviso',
    ],

];
