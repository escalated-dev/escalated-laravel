<?php

return [

    'install' => [
        'installing' => 'Escalated 설치 중...',
        'publishing_config' => '설정 파일 게시 중',
        'publishing_migrations' => '마이그레이션 게시 중',
        'migrations_already_published' => 'Escalated 마이그레이션 :count개가 이미 게시되어 건너뜁니다. --force 옵션으로 다시 실행하면 교체됩니다.',
        'publishing_views' => '이메일 뷰 게시 중',
        'installing_npm' => 'npm 패키지 설치 중',
        'npm_manual' => 'npm 패키지를 자동으로 설치할 수 없습니다. 수동으로 실행하세요:',
        'user_model_not_found' => 'User 모델을 찾을 수 없습니다. 수동으로 설정해야 합니다.',
        'user_model_already_configured' => 'User 모델이 이미 Ticketable을 구현하고 있습니다.',
        'user_model_confirm' => 'User 모델을 자동으로 설정하여 Ticketable을 구현하시겠습니까?',
        'user_model_configured' => 'User 모델이 성공적으로 설정되었습니다.',
        'user_model_failed' => 'User 모델을 자동으로 설정할 수 없습니다: :error',
        'success' => 'Escalated가 성공적으로 설치되었습니다!',
        'next_steps' => '다음 단계:',
        'step_ticketable' => 'User 모델에 Ticketable 인터페이스를 구현하세요:',
        'step_gates' => 'AuthServiceProvider에서 인가 게이트를 정의하세요:',
        'step_migrate' => '마이그레이션을 실행하세요:',
        'step_tailwind' => 'Tailwind 콘텐츠 설정에 Escalated 페이지를 추가하세요:',
        'step_inertia' => 'app.ts에 Inertia 페이지 리졸버와 플러그인을 추가하세요:',
        'step_visit' => '/support를 방문하여 고객 포털을 확인하세요',
        'running_migrations' => '마이그레이션 실행 중',
        'seeding_permissions' => '권한과 역할 시딩 중',
        'run_migrations_confirm' => '지금 마이그레이션을 실행하고 기본 권한을 시드할까요?',
        'step_seed' => '기본 권한과 역할 시딩:',
    ],

];
