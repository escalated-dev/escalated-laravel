<?php

return [

    'status' => [
        'open' => 'Aberto',
        'in_progress' => 'Em andamento',
        'waiting_on_customer' => 'Aguardando cliente',
        'waiting_on_agent' => 'Aguardando agente',
        'escalated' => 'Escalado',
        'resolved' => 'Resolvido',
        'closed' => 'Fechado',
        'snoozed' => 'Adiado',
        'unsnoozed' => 'Reativado',
        'reopened' => 'Reaberto',
        'live' => 'Ao vivo',
    ],

    'priority' => [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'urgent' => 'Urgente',
        'critical' => 'Crítica',
    ],

    'activity_type' => [
        'status_changed' => 'Status alterado',
        'assigned' => 'Atribuído',
        'unassigned' => 'Atribuição removida',
        'priority_changed' => 'Prioridade alterada',
        'tag_added' => 'Tag adicionada',
        'tag_removed' => 'Tag removida',
        'escalated' => 'Escalado',
        'sla_breached' => 'SLA violado',
        'replied' => 'Respondido',
        'note_added' => 'Nota adicionada',
        'department_changed' => 'Departamento alterado',
        'reopened' => 'Reaberto',
        'resolved' => 'Resolvido',
        'closed' => 'Fechado',
        'snoozed' => 'Adiado',
        'unsnoozed' => 'Reativado',
        'ticket_split' => 'Ticket dividido',
    ],

];
