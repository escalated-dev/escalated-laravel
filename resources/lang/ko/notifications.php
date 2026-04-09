<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] 새 티켓: :subject',
        'line1' => '새 지원 티켓이 생성되었습니다.',
        'subject_line' => '**제목:** :subject',
        'priority_line' => '**우선순위:** :priority',
        'action' => '티켓 보기',
        'closing' => '지원 시스템을 이용해 주셔서 감사합니다.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] 티켓이 귀하에게 배정되었습니다',
        'line1' => '티켓이 귀하에게 배정되었습니다.',
        'subject_line' => '**제목:** :subject',
        'priority_line' => '**우선순위:** :priority',
        'action' => '티켓 보기',
        'closing' => '가능한 빨리 검토하고 응답해 주세요.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => '티켓에 새 답변이 추가되었습니다.',
        'action' => '티켓 보기',
        'closing' => '지원 시스템을 이용해 주셔서 감사합니다.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] 티켓 해결됨',
        'line1' => '지원 티켓이 해결되었습니다.',
        'subject_line' => '**제목:** :subject',
        'reopen_line' => '추가 지원이 필요하시면 이 티켓을 다시 열 수 있습니다.',
        'action' => '티켓 보기',
        'closing' => '지원 시스템을 이용해 주셔서 감사합니다.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] 상태 업데이트: :status',
        'line1' => '티켓의 상태가 업데이트되었습니다.',
        'from_line' => '**이전:** :status',
        'to_line' => '**변경:** :status',
        'action' => '티켓 보기',
        'closing' => '지원 시스템을 이용해 주셔서 감사합니다.',
    ],

    'sla_breach' => [
        'subject' => '[SLA 위반] [:reference] :type SLA 위반',
        'type_first_response' => '첫 번째 응답',
        'type_resolution' => '해결',
        'line1' => '티켓 :reference에서 SLA가 위반되었습니다.',
        'type_line' => '**유형:** :type SLA',
        'subject_line' => '**제목:** :subject',
        'priority_line' => '**우선순위:** :priority',
        'action' => '티켓 보기',
        'closing' => '즉각적인 조치가 필요합니다.',
    ],

    'ticket_escalated' => [
        'subject' => '[에스컬레이션] [:reference] :subject',
        'line1' => '티켓이 에스컬레이션되었습니다.',
        'subject_line' => '**제목:** :subject',
        'priority_line' => '**우선순위:** :priority',
        'reason_line' => '**사유:** :reason',
        'action' => '티켓 보기',
        'closing' => '즉각적인 조치가 필요합니다.',
    ],

];
