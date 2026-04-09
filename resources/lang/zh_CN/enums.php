<?php

return [

    'status' => [
        'open' => '待处理',
        'in_progress' => '处理中',
        'waiting_on_customer' => '等待客户',
        'waiting_on_agent' => '等待客服',
        'escalated' => '已升级',
        'resolved' => '已解决',
        'closed' => '已关闭',
        'snoozed' => '已暂停',
        'unsnoozed' => '已恢复',
        'reopened' => '已重新打开',
        'live' => '实时',
    ],

    'priority' => [
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'urgent' => '紧急',
        'critical' => '严重',
    ],

    'activity_type' => [
        'status_changed' => '状态已更改',
        'assigned' => '已分配',
        'unassigned' => '已取消分配',
        'priority_changed' => '优先级已更改',
        'tag_added' => '标签已添加',
        'tag_removed' => '标签已移除',
        'escalated' => '已升级',
        'sla_breached' => 'SLA 已违反',
        'replied' => '已回复',
        'note_added' => '备注已添加',
        'department_changed' => '部门已更改',
        'reopened' => '已重新打开',
        'resolved' => '已解决',
        'closed' => '已关闭',
        'snoozed' => '已暂停',
        'unsnoozed' => '已恢复',
        'ticket_split' => '工单已拆分',
    ],

];
