<?php

return [

    'ticket' => [
        'reply_sent' => '답변이 전송되었습니다.',
        'note_added' => '메모가 추가되었습니다.',
        'assigned' => '티켓이 배정되었습니다.',
        'status_updated' => '상태가 업데이트되었습니다.',
        'priority_updated' => '우선순위가 업데이트되었습니다.',
        'tags_updated' => '태그가 업데이트되었습니다.',
        'department_updated' => '부서가 업데이트되었습니다.',
        'macro_applied' => '매크로 ":name"이(가) 적용되었습니다.',
        'following' => '티켓을 팔로우합니다.',
        'unfollowed' => '티켓 팔로우를 해제했습니다.',
        'only_internal_notes_pinned' => '내부 메모만 고정할 수 있습니다.',
        'updated' => '티켓이 업데이트되었습니다.',
        'created' => '티켓이 성공적으로 생성되었습니다.',
        'closed' => '티켓이 종료되었습니다.',
        'reopened' => '티켓이 다시 열렸습니다.',
        'customers_cannot_close' => '고객은 티켓을 종료할 수 없습니다.',
    ],

    'guest' => [
        'created' => '티켓이 생성되었습니다. 이 링크를 저장하여 티켓 상태를 확인하세요.',
        'ticket_closed' => '이 티켓은 종료되었습니다.',
    ],

    'bulk' => [
        'updated' => ':count개의 티켓이 업데이트되었습니다.',
    ],

    'rating' => [
        'only_resolved_closed' => '해결되거나 종료된 티켓만 평가할 수 있습니다.',
        'already_rated' => '이 티켓은 이미 평가되었습니다.',
        'thanks' => '피드백 감사합니다!',
    ],

    'canned_response' => [
        'created' => '미리 작성된 답변이 생성되었습니다.',
        'updated' => '미리 작성된 답변이 업데이트되었습니다.',
        'deleted' => '미리 작성된 답변이 삭제되었습니다.',
    ],

    'department' => [
        'created' => '부서가 생성되었습니다.',
        'updated' => '부서가 업데이트되었습니다.',
        'deleted' => '부서가 삭제되었습니다.',
    ],

    'tag' => [
        'created' => '태그가 생성되었습니다.',
        'updated' => '태그가 업데이트되었습니다.',
        'deleted' => '태그가 삭제되었습니다.',
    ],

    'macro' => [
        'created' => '매크로가 생성되었습니다.',
        'updated' => '매크로가 업데이트되었습니다.',
        'deleted' => '매크로가 삭제되었습니다.',
    ],

    'escalation_rule' => [
        'created' => '규칙이 생성되었습니다.',
        'updated' => '규칙이 업데이트되었습니다.',
        'deleted' => '규칙이 삭제되었습니다.',
    ],

    'sla_policy' => [
        'created' => 'SLA 정책이 생성되었습니다.',
        'updated' => 'SLA 정책이 업데이트되었습니다.',
        'deleted' => 'SLA 정책이 삭제되었습니다.',
    ],

    'settings' => [
        'updated' => '설정이 업데이트되었습니다.',
    ],

    'plugin' => [
        'uploaded' => '플러그인이 성공적으로 업로드되었습니다. 이제 활성화할 수 있습니다.',
        'upload_failed' => '플러그인 업로드 실패: :error',
        'activated' => '플러그인이 성공적으로 활성화되었습니다.',
        'activate_failed' => '플러그인 활성화 실패: :error',
        'deactivated' => '플러그인이 성공적으로 비활성화되었습니다.',
        'deactivate_failed' => '플러그인 비활성화 실패: :error',
        'composer_delete' => 'Composer 플러그인은 삭제할 수 없습니다. 대신 Composer를 통해 패키지를 제거하세요.',
        'deleted' => '플러그인이 성공적으로 삭제되었습니다.',
        'delete_failed' => '플러그인 삭제 실패: :error',
    ],

    'middleware' => [
        'not_admin' => '지원 관리자 권한이 없습니다.',
        'not_agent' => '지원 담당자 권한이 없습니다.',
    ],

    'inbound_email' => [
        'disabled' => '수신 이메일이 비활성화되어 있습니다.',
        'unknown_adapter' => '알 수 없는 어댑터입니다.',
        'invalid_signature' => '유효하지 않은 서명입니다.',
        'processing_failed' => '처리에 실패했습니다.',
    ],

];
