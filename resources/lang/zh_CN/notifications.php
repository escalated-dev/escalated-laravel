<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] 新工单：:subject',
        'line1' => '已创建一个新的支持工单。',
        'subject_line' => '**主题：** :subject',
        'priority_line' => '**优先级：** :priority',
        'action' => '查看工单',
        'closing' => '感谢您使用我们的支持系统。',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] 工单已分配给您',
        'line1' => '一个工单已分配给您。',
        'subject_line' => '**主题：** :subject',
        'priority_line' => '**优先级：** :priority',
        'action' => '查看工单',
        'closing' => '请尽快审阅并回复。',
    ],

    'ticket_reply' => [
        'subject' => '回复：[:reference] :subject',
        'line1' => '您的工单收到了新的回复。',
        'action' => '查看工单',
        'closing' => '感谢您使用我们的支持系统。',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] 工单已解决',
        'line1' => '您的支持工单已解决。',
        'subject_line' => '**主题：** :subject',
        'reopen_line' => '如果您需要进一步帮助，可以重新打开此工单。',
        'action' => '查看工单',
        'closing' => '感谢您使用我们的支持系统。',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] 状态已更新：:status',
        'line1' => '您的工单状态已更新。',
        'from_line' => '**从：** :status',
        'to_line' => '**到：** :status',
        'action' => '查看工单',
        'closing' => '感谢您使用我们的支持系统。',
    ],

    'sla_breach' => [
        'subject' => '[SLA 违规] [:reference] :type SLA 已违反',
        'type_first_response' => '首次响应',
        'type_resolution' => '解决',
        'line1' => '工单 :reference 的 SLA 已被违反。',
        'type_line' => '**类型：** :type SLA',
        'subject_line' => '**主题：** :subject',
        'priority_line' => '**优先级：** :priority',
        'action' => '查看工单',
        'closing' => '需要立即处理。',
    ],

    'ticket_escalated' => [
        'subject' => '[已升级] [:reference] :subject',
        'line1' => '一个工单已被升级。',
        'subject_line' => '**主题：** :subject',
        'priority_line' => '**优先级：** :priority',
        'reason_line' => '**原因：** :reason',
        'action' => '查看工单',
        'closing' => '需要立即处理。',
    ],

];
