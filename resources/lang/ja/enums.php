<?php

return [

    'status' => [
        'open' => 'オープン',
        'in_progress' => '対応中',
        'waiting_on_customer' => '顧客の返答待ち',
        'waiting_on_agent' => '担当者の対応待ち',
        'escalated' => 'エスカレーション済み',
        'resolved' => '解決済み',
        'closed' => 'クローズ',
        'snoozed' => 'スヌーズ中',
        'unsnoozed' => 'スヌーズ解除',
        'reopened' => '再オープン',
        'live' => 'ライブ',
    ],

    'priority' => [
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'urgent' => '緊急',
        'critical' => '重大',
    ],

    'activity_type' => [
        'status_changed' => 'ステータス変更',
        'assigned' => '担当者を割り当て',
        'unassigned' => '担当者の割り当て解除',
        'priority_changed' => '優先度変更',
        'tag_added' => 'タグ追加',
        'tag_removed' => 'タグ削除',
        'escalated' => 'エスカレーション',
        'sla_breached' => 'SLA 違反',
        'replied' => '返信済み',
        'note_added' => 'メモ追加',
        'department_changed' => '部門変更',
        'reopened' => '再オープン',
        'resolved' => '解決済み',
        'closed' => 'クローズ',
        'snoozed' => 'スヌーズ',
        'unsnoozed' => 'スヌーズ解除',
        'ticket_split' => 'チケット分割',
    ],

];
