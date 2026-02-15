<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Nuevo ticket: :subject',
        'line1' => 'Se ha creado un nuevo ticket de soporte.',
        'subject_line' => '**Asunto:** :subject',
        'priority_line' => '**Prioridad:** :priority',
        'action' => 'Ver ticket',
        'closing' => 'Gracias por utilizar nuestro sistema de soporte.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket asignado a usted',
        'line1' => 'Se le ha asignado un ticket.',
        'subject_line' => '**Asunto:** :subject',
        'priority_line' => '**Prioridad:** :priority',
        'action' => 'Ver ticket',
        'closing' => 'Por favor, revise y responda a la mayor brevedad posible.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'Se ha añadido una nueva respuesta a su ticket.',
        'action' => 'Ver ticket',
        'closing' => 'Gracias por utilizar nuestro sistema de soporte.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket resuelto',
        'line1' => 'Su ticket de soporte ha sido resuelto.',
        'subject_line' => '**Asunto:** :subject',
        'reopen_line' => 'Si necesita más ayuda, puede reabrir este ticket.',
        'action' => 'Ver ticket',
        'closing' => 'Gracias por utilizar nuestro sistema de soporte.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Estado actualizado: :status',
        'line1' => 'El estado de su ticket ha sido actualizado.',
        'from_line' => '**De:** :status',
        'to_line' => '**A:** :status',
        'action' => 'Ver ticket',
        'closing' => 'Gracias por utilizar nuestro sistema de soporte.',
    ],

    'sla_breach' => [
        'subject' => '[Incumplimiento SLA] [:reference] SLA de :type incumplido',
        'type_first_response' => 'Primera respuesta',
        'type_resolution' => 'Resolución',
        'line1' => 'Se ha incumplido un SLA en el ticket :reference.',
        'type_line' => '**Tipo:** SLA de :type',
        'subject_line' => '**Asunto:** :subject',
        'priority_line' => '**Prioridad:** :priority',
        'action' => 'Ver ticket',
        'closing' => 'Se requiere atención inmediata.',
    ],

    'ticket_escalated' => [
        'subject' => '[Escalado] [:reference] :subject',
        'line1' => 'Un ticket ha sido escalado.',
        'subject_line' => '**Asunto:** :subject',
        'priority_line' => '**Prioridad:** :priority',
        'reason_line' => '**Motivo:** :reason',
        'action' => 'Ver ticket',
        'closing' => 'Se requiere atención inmediata.',
    ],

];
