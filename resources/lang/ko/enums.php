<?php

return [

    'status' => [
        'open' => '열림',
        'in_progress' => '진행 중',
        'waiting_on_customer' => '고객 대기 중',
        'waiting_on_agent' => '담당자 대기 중',
        'escalated' => '에스컬레이션됨',
        'resolved' => '해결됨',
        'closed' => '종료됨',
        'snoozed' => '일시 중지됨',
        'unsnoozed' => '일시 중지 해제됨',
        'reopened' => '다시 열림',
        'live' => '실시간',
    ],

    'priority' => [
        'low' => '낮음',
        'medium' => '보통',
        'high' => '높음',
        'urgent' => '긴급',
        'critical' => '심각',
    ],

    'activity_type' => [
        'status_changed' => '상태 변경',
        'assigned' => '배정됨',
        'unassigned' => '배정 해제됨',
        'priority_changed' => '우선순위 변경',
        'tag_added' => '태그 추가됨',
        'tag_removed' => '태그 제거됨',
        'escalated' => '에스컬레이션됨',
        'sla_breached' => 'SLA 위반',
        'replied' => '답변됨',
        'note_added' => '메모 추가됨',
        'department_changed' => '부서 변경됨',
        'reopened' => '다시 열림',
        'resolved' => '해결됨',
        'closed' => '종료됨',
        'snoozed' => '일시 중지됨',
        'unsnoozed' => '일시 중지 해제됨',
        'ticket_split' => '티켓 분할됨',
    ],

];
