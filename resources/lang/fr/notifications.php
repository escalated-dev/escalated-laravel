<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Nouveau ticket : :subject',
        'line1' => 'Un nouveau ticket de support a été créé.',
        'subject_line' => '**Objet :** :subject',
        'priority_line' => '**Priorité :** :priority',
        'action' => 'Voir le ticket',
        'closing' => 'Merci d\'utiliser notre système de support.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket qui vous est assigné',
        'line1' => 'Un ticket vous a été assigné.',
        'subject_line' => '**Objet :** :subject',
        'priority_line' => '**Priorité :** :priority',
        'action' => 'Voir le ticket',
        'closing' => 'Veuillez examiner et répondre dans les meilleurs délais.',
    ],

    'ticket_reply' => [
        'subject' => 'Re : [:reference] :subject',
        'line1' => 'Une nouvelle réponse a été ajoutée à votre ticket.',
        'action' => 'Voir le ticket',
        'closing' => 'Merci d\'utiliser notre système de support.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket résolu',
        'line1' => 'Votre ticket de support a été résolu.',
        'subject_line' => '**Objet :** :subject',
        'reopen_line' => 'Si vous avez besoin d\'aide supplémentaire, vous pouvez rouvrir ce ticket.',
        'action' => 'Voir le ticket',
        'closing' => 'Merci d\'utiliser notre système de support.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Statut mis à jour : :status',
        'line1' => 'Le statut de votre ticket a été mis à jour.',
        'from_line' => '**De :** :status',
        'to_line' => '**À :** :status',
        'action' => 'Voir le ticket',
        'closing' => 'Merci d\'utiliser notre système de support.',
    ],

    'sla_breach' => [
        'subject' => '[Violation SLA] [:reference] SLA de :type violé',
        'type_first_response' => 'Première réponse',
        'type_resolution' => 'Résolution',
        'line1' => 'Un SLA a été violé sur le ticket :reference.',
        'type_line' => '**Type :** SLA de :type',
        'subject_line' => '**Objet :** :subject',
        'priority_line' => '**Priorité :** :priority',
        'action' => 'Voir le ticket',
        'closing' => 'Une attention immédiate est requise.',
    ],

    'ticket_escalated' => [
        'subject' => '[Escaladé] [:reference] :subject',
        'line1' => 'Un ticket a été escaladé.',
        'subject_line' => '**Objet :** :subject',
        'priority_line' => '**Priorité :** :priority',
        'reason_line' => '**Raison :** :reason',
        'action' => 'Voir le ticket',
        'closing' => 'Une attention immédiate est requise.',
    ],

];
