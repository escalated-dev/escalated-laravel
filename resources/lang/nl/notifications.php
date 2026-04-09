<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Nieuw ticket: :subject',
        'line1' => 'Er is een nieuw supportticket aangemaakt.',
        'subject_line' => '**Onderwerp:** :subject',
        'priority_line' => '**Prioriteit:** :priority',
        'action' => 'Ticket bekijken',
        'closing' => 'Bedankt voor het gebruik van ons supportsysteem.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket aan u toegewezen',
        'line1' => 'Een ticket is aan u toegewezen.',
        'subject_line' => '**Onderwerp:** :subject',
        'priority_line' => '**Prioriteit:** :priority',
        'action' => 'Ticket bekijken',
        'closing' => 'Gelieve te beoordelen en zo spoedig mogelijk te reageren.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'Er is een nieuw antwoord toegevoegd aan uw ticket.',
        'action' => 'Ticket bekijken',
        'closing' => 'Bedankt voor het gebruik van ons supportsysteem.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket opgelost',
        'line1' => 'Uw supportticket is opgelost.',
        'subject_line' => '**Onderwerp:** :subject',
        'reopen_line' => 'Als u verdere hulp nodig heeft, kunt u dit ticket heropenen.',
        'action' => 'Ticket bekijken',
        'closing' => 'Bedankt voor het gebruik van ons supportsysteem.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Status bijgewerkt: :status',
        'line1' => 'De status van uw ticket is bijgewerkt.',
        'from_line' => '**Van:** :status',
        'to_line' => '**Naar:** :status',
        'action' => 'Ticket bekijken',
        'closing' => 'Bedankt voor het gebruik van ons supportsysteem.',
    ],

    'sla_breach' => [
        'subject' => '[SLA-schending] [:reference] :type SLA geschonden',
        'type_first_response' => 'Eerste reactie',
        'type_resolution' => 'Oplossing',
        'line1' => 'Een SLA is geschonden bij ticket :reference.',
        'type_line' => '**Type:** :type SLA',
        'subject_line' => '**Onderwerp:** :subject',
        'priority_line' => '**Prioriteit:** :priority',
        'action' => 'Ticket bekijken',
        'closing' => 'Onmiddellijke aandacht is vereist.',
    ],

    'ticket_escalated' => [
        'subject' => '[Geëscaleerd] [:reference] :subject',
        'line1' => 'Een ticket is geëscaleerd.',
        'subject_line' => '**Onderwerp:** :subject',
        'priority_line' => '**Prioriteit:** :priority',
        'reason_line' => '**Reden:** :reason',
        'action' => 'Ticket bekijken',
        'closing' => 'Onmiddellijke aandacht is vereist.',
    ],

];
