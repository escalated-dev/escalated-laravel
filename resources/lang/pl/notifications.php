<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Nowe zgłoszenie: :subject',
        'line1' => 'Utworzono nowe zgłoszenie.',
        'subject_line' => '**Temat:** :subject',
        'priority_line' => '**Priorytet:** :priority',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Dziękujemy za korzystanie z naszego systemu wsparcia.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Zgłoszenie przypisane do Ciebie',
        'line1' => 'Zgłoszenie zostało przypisane do Ciebie.',
        'subject_line' => '**Temat:** :subject',
        'priority_line' => '**Priorytet:** :priority',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Prosimy o przejrzenie i odpowiedź w najwcześniejszym możliwym terminie.',
    ],

    'ticket_reply' => [
        'subject' => 'Odp: [:reference] :subject',
        'line1' => 'Dodano nową odpowiedź do Twojego zgłoszenia.',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Dziękujemy za korzystanie z naszego systemu wsparcia.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Zgłoszenie rozwiązane',
        'line1' => 'Twoje zgłoszenie zostało rozwiązane.',
        'subject_line' => '**Temat:** :subject',
        'reopen_line' => 'Jeśli potrzebujesz dalszej pomocy, możesz ponownie otworzyć to zgłoszenie.',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Dziękujemy za korzystanie z naszego systemu wsparcia.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Status zaktualizowany: :status',
        'line1' => 'Status Twojego zgłoszenia został zaktualizowany.',
        'from_line' => '**Z:** :status',
        'to_line' => '**Na:** :status',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Dziękujemy za korzystanie z naszego systemu wsparcia.',
    ],

    'sla_breach' => [
        'subject' => '[Naruszenie SLA] [:reference] Naruszenie SLA :type',
        'type_first_response' => 'Pierwsza odpowiedź',
        'type_resolution' => 'Rozwiązanie',
        'line1' => 'SLA zostało naruszone w zgłoszeniu :reference.',
        'type_line' => '**Typ:** SLA :type',
        'subject_line' => '**Temat:** :subject',
        'priority_line' => '**Priorytet:** :priority',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Wymagana jest natychmiastowa uwaga.',
    ],

    'ticket_escalated' => [
        'subject' => '[Eskalacja] [:reference] :subject',
        'line1' => 'Zgłoszenie zostało eskalowane.',
        'subject_line' => '**Temat:** :subject',
        'priority_line' => '**Priorytet:** :priority',
        'reason_line' => '**Powód:** :reason',
        'action' => 'Zobacz zgłoszenie',
        'closing' => 'Wymagana jest natychmiastowa uwaga.',
    ],

];
