<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] 新しいチケット: :subject',
        'line1' => '新しいサポートチケットが作成されました。',
        'subject_line' => '**件名:** :subject',
        'priority_line' => '**優先度:** :priority',
        'action' => 'チケットを表示',
        'closing' => 'サポートシステムをご利用いただきありがとうございます。',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] チケットがあなたに割り当てられました',
        'line1' => 'チケットがあなたに割り当てられました。',
        'subject_line' => '**件名:** :subject',
        'priority_line' => '**優先度:** :priority',
        'action' => 'チケットを表示',
        'closing' => 'できるだけ早く確認し、対応してください。',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'チケットに新しい返信が追加されました。',
        'action' => 'チケットを表示',
        'closing' => 'サポートシステムをご利用いただきありがとうございます。',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] チケット解決済み',
        'line1' => 'サポートチケットが解決されました。',
        'subject_line' => '**件名:** :subject',
        'reopen_line' => 'さらにサポートが必要な場合は、このチケットを再開できます。',
        'action' => 'チケットを表示',
        'closing' => 'サポートシステムをご利用いただきありがとうございます。',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] ステータス更新: :status',
        'line1' => 'チケットのステータスが更新されました。',
        'from_line' => '**変更前:** :status',
        'to_line' => '**変更後:** :status',
        'action' => 'チケットを表示',
        'closing' => 'サポートシステムをご利用いただきありがとうございます。',
    ],

    'sla_breach' => [
        'subject' => '[SLA 違反] [:reference] :type SLA 違反',
        'type_first_response' => '初回応答',
        'type_resolution' => '解決',
        'line1' => 'チケット :reference で SLA が違反されました。',
        'type_line' => '**種類:** :type SLA',
        'subject_line' => '**件名:** :subject',
        'priority_line' => '**優先度:** :priority',
        'action' => 'チケットを表示',
        'closing' => '早急な対応が必要です。',
    ],

    'ticket_escalated' => [
        'subject' => '[エスカレーション] [:reference] :subject',
        'line1' => 'チケットがエスカレーションされました。',
        'subject_line' => '**件名:** :subject',
        'priority_line' => '**優先度:** :priority',
        'reason_line' => '**理由:** :reason',
        'action' => 'チケットを表示',
        'closing' => '早急な対応が必要です。',
    ],

];
