<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Novo ticket: :subject',
        'line1' => 'Um novo ticket de suporte foi criado.',
        'subject_line' => '**Assunto:** :subject',
        'priority_line' => '**Prioridade:** :priority',
        'action' => 'Ver ticket',
        'closing' => 'Obrigado por utilizar nosso sistema de suporte.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Ticket atribuído a você',
        'line1' => 'Um ticket foi atribuído a você.',
        'subject_line' => '**Assunto:** :subject',
        'priority_line' => '**Prioridade:** :priority',
        'action' => 'Ver ticket',
        'closing' => 'Por favor, revise e responda o mais breve possível.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'Uma nova resposta foi adicionada ao seu ticket.',
        'action' => 'Ver ticket',
        'closing' => 'Obrigado por utilizar nosso sistema de suporte.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Ticket resolvido',
        'line1' => 'Seu ticket de suporte foi resolvido.',
        'subject_line' => '**Assunto:** :subject',
        'reopen_line' => 'Se precisar de mais ajuda, você pode reabrir este ticket.',
        'action' => 'Ver ticket',
        'closing' => 'Obrigado por utilizar nosso sistema de suporte.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Status atualizado: :status',
        'line1' => 'O status do seu ticket foi atualizado.',
        'from_line' => '**De:** :status',
        'to_line' => '**Para:** :status',
        'action' => 'Ver ticket',
        'closing' => 'Obrigado por utilizar nosso sistema de suporte.',
    ],

    'sla_breach' => [
        'subject' => '[Violação de SLA] [:reference] SLA de :type violado',
        'type_first_response' => 'Primeira resposta',
        'type_resolution' => 'Resolução',
        'line1' => 'Um SLA foi violado no ticket :reference.',
        'type_line' => '**Tipo:** SLA de :type',
        'subject_line' => '**Assunto:** :subject',
        'priority_line' => '**Prioridade:** :priority',
        'action' => 'Ver ticket',
        'closing' => 'Atenção imediata é necessária.',
    ],

    'ticket_escalated' => [
        'subject' => '[Escalado] [:reference] :subject',
        'line1' => 'Um ticket foi escalado.',
        'subject_line' => '**Assunto:** :subject',
        'priority_line' => '**Prioridade:** :priority',
        'reason_line' => '**Motivo:** :reason',
        'action' => 'Ver ticket',
        'closing' => 'Atenção imediata é necessária.',
    ],

];
