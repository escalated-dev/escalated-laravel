<?php

return [

    'status' => [
        'open' => 'Открыт',
        'in_progress' => 'В работе',
        'waiting_on_customer' => 'Ожидание клиента',
        'waiting_on_agent' => 'Ожидание агента',
        'escalated' => 'Эскалирован',
        'resolved' => 'Решён',
        'closed' => 'Закрыт',
        'snoozed' => 'Отложен',
        'unsnoozed' => 'Возобновлён',
        'reopened' => 'Переоткрыт',
        'live' => 'Активен',
    ],

    'priority' => [
        'low' => 'Низкий',
        'medium' => 'Средний',
        'high' => 'Высокий',
        'urgent' => 'Срочный',
        'critical' => 'Критический',
    ],

    'activity_type' => [
        'status_changed' => 'Статус изменён',
        'assigned' => 'Назначен',
        'unassigned' => 'Назначение снято',
        'priority_changed' => 'Приоритет изменён',
        'tag_added' => 'Тег добавлен',
        'tag_removed' => 'Тег удалён',
        'escalated' => 'Эскалирован',
        'sla_breached' => 'SLA нарушен',
        'replied' => 'Ответ дан',
        'note_added' => 'Заметка добавлена',
        'department_changed' => 'Отдел изменён',
        'reopened' => 'Переоткрыт',
        'resolved' => 'Решён',
        'closed' => 'Закрыт',
        'snoozed' => 'Отложен',
        'unsnoozed' => 'Возобновлён',
        'ticket_split' => 'Тикет разделён',
    ],

];
