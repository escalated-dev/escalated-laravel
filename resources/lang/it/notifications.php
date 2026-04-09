<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Nuovo ticket: :subject',
        'line1' => 'È stato creato un nuovo ticket di supporto.',
        'subject_line' => '**Oggetto:** :subject',
        'priority_line' => '**Priorità:** :priority',
        'action' => 'Visualizza ticket',
        'closing' => 'Grazie per aver utilizzato il nostro sistema di supporto.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket assegnato a te',
        'line1' => 'Un ticket ti è stato assegnato.',
        'subject_line' => '**Oggetto:** :subject',
        'priority_line' => '**Priorità:** :priority',
        'action' => 'Visualizza ticket',
        'closing' => 'Si prega di esaminare e rispondere al più presto.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'Una nuova risposta è stata aggiunta al tuo ticket.',
        'action' => 'Visualizza ticket',
        'closing' => 'Grazie per aver utilizzato il nostro sistema di supporto.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket risolto',
        'line1' => 'Il tuo ticket di supporto è stato risolto.',
        'subject_line' => '**Oggetto:** :subject',
        'reopen_line' => 'Se hai bisogno di ulteriore assistenza, puoi riaprire questo ticket.',
        'action' => 'Visualizza ticket',
        'closing' => 'Grazie per aver utilizzato il nostro sistema di supporto.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Stato aggiornato: :status',
        'line1' => 'Lo stato del tuo ticket è stato aggiornato.',
        'from_line' => '**Da:** :status',
        'to_line' => '**A:** :status',
        'action' => 'Visualizza ticket',
        'closing' => 'Grazie per aver utilizzato il nostro sistema di supporto.',
    ],

    'sla_breach' => [
        'subject' => '[Violazione SLA] [:reference] SLA :type violato',
        'type_first_response' => 'Prima risposta',
        'type_resolution' => 'Risoluzione',
        'line1' => 'Un SLA è stato violato sul ticket :reference.',
        'type_line' => '**Tipo:** SLA :type',
        'subject_line' => '**Oggetto:** :subject',
        'priority_line' => '**Priorità:** :priority',
        'action' => 'Visualizza ticket',
        'closing' => 'È richiesta attenzione immediata.',
    ],

    'ticket_escalated' => [
        'subject' => '[Escalato] [:reference] :subject',
        'line1' => 'Un ticket è stato escalato.',
        'subject_line' => '**Oggetto:** :subject',
        'priority_line' => '**Priorità:** :priority',
        'reason_line' => '**Motivo:** :reason',
        'action' => 'Visualizza ticket',
        'closing' => 'È richiesta attenzione immediata.',
    ],

];
