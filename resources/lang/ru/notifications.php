<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Новый тикет: :subject',
        'line1' => 'Создан новый тикет поддержки.',
        'subject_line' => '**Тема:** :subject',
        'priority_line' => '**Приоритет:** :priority',
        'action' => 'Просмотреть тикет',
        'closing' => 'Спасибо за использование нашей системы поддержки.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Тикет назначен вам',
        'line1' => 'Вам назначен тикет.',
        'subject_line' => '**Тема:** :subject',
        'priority_line' => '**Приоритет:** :priority',
        'action' => 'Просмотреть тикет',
        'closing' => 'Пожалуйста, рассмотрите и ответьте при первой возможности.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'К вашему тикету добавлен новый ответ.',
        'action' => 'Просмотреть тикет',
        'closing' => 'Спасибо за использование нашей системы поддержки.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Тикет решён',
        'line1' => 'Ваш тикет поддержки был решён.',
        'subject_line' => '**Тема:** :subject',
        'reopen_line' => 'Если вам нужна дополнительная помощь, вы можете повторно открыть этот тикет.',
        'action' => 'Просмотреть тикет',
        'closing' => 'Спасибо за использование нашей системы поддержки.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Статус обновлён: :status',
        'line1' => 'Статус вашего тикета был обновлён.',
        'from_line' => '**С:** :status',
        'to_line' => '**На:** :status',
        'action' => 'Просмотреть тикет',
        'closing' => 'Спасибо за использование нашей системы поддержки.',
    ],

    'sla_breach' => [
        'subject' => '[Нарушение SLA] [:reference] SLA :type нарушен',
        'type_first_response' => 'Первый ответ',
        'type_resolution' => 'Решение',
        'line1' => 'SLA был нарушен по тикету :reference.',
        'type_line' => '**Тип:** SLA :type',
        'subject_line' => '**Тема:** :subject',
        'priority_line' => '**Приоритет:** :priority',
        'action' => 'Просмотреть тикет',
        'closing' => 'Требуется немедленное внимание.',
    ],

    'ticket_escalated' => [
        'subject' => '[Эскалация] [:reference] :subject',
        'line1' => 'Тикет был эскалирован.',
        'subject_line' => '**Тема:** :subject',
        'priority_line' => '**Приоритет:** :priority',
        'reason_line' => '**Причина:** :reason',
        'action' => 'Просмотреть тикет',
        'closing' => 'Требуется немедленное внимание.',
    ],

];
