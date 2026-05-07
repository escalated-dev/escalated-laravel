<?php

return [

    'install' => [
        'installing' => 'Escalated をインストールしています...',
        'publishing_config' => '設定ファイルを公開しています',
        'publishing_migrations' => 'マイグレーションを公開しています',
        'migrations_already_published' => 'Escalated のマイグレーション :count 件は既に公開済みのためスキップしました。--force を付けて再実行すると置き換えます。',
        'publishing_views' => 'メールビューを公開しています',
        'installing_npm' => 'npm パッケージをインストールしています',
        'npm_manual' => 'npm パッケージを自動でインストールできませんでした。手動で実行してください:',
        'user_model_not_found' => 'User モデルが見つかりませんでした。手動で設定する必要があります。',
        'user_model_already_configured' => 'User モデルはすでに Ticketable を実装しています。',
        'user_model_confirm' => 'User モデルを自動的に設定して Ticketable を実装しますか？',
        'user_model_configured' => 'User モデルの設定が完了しました。',
        'user_model_failed' => 'User モデルを自動で設定できませんでした: :error',
        'success' => 'Escalated のインストールが完了しました！',
        'next_steps' => '次のステップ:',
        'step_ticketable' => 'User モデルに Ticketable インターフェースを実装してください:',
        'step_gates' => 'AuthServiceProvider で認可ゲートを定義してください:',
        'step_migrate' => 'マイグレーションを実行してください:',
        'step_tailwind' => 'Tailwind のコンテンツ設定に Escalated のページを追加してください:',
        'step_inertia' => 'app.ts に Inertia のページリゾルバーとプラグインを追加してください:',
        'step_visit' => '/support にアクセスしてカスタマーポータルを確認してください',
        'running_migrations' => 'マイグレーションを実行中',
        'seeding_permissions' => '権限とロールをシード中',
        'run_migrations_confirm' => '今すぐマイグレーションを実行し、デフォルトの権限をシードしますか？',
        'step_seed' => 'デフォルトの権限とロールをシードする：',
    ],

];
