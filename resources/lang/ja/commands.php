<?php

return [

    'install' => [
        'installing' => 'Escalated をインストールしています...',
        'publishingConfig' => '設定ファイルを公開しています',
        'publishingMigrations' => 'マイグレーションを公開しています',
        'migrationsAlreadyPublished' => 'Escalated のマイグレーション :count 件は既に公開済みのためスキップしました。--force を付けて再実行すると置き換えます。',
        'publishingViews' => 'メールビューを公開しています',
        'installingNpm' => 'npm パッケージをインストールしています',
        'npmManual' => 'npm パッケージを自動でインストールできませんでした。手動で実行してください:',
        'userModelNotFound' => 'User モデルが見つかりませんでした。手動で設定する必要があります。',
        'userModelAlreadyConfigured' => 'User モデルはすでに Ticketable を実装しています。',
        'userModelConfirm' => 'User モデルを自動的に設定して Ticketable を実装しますか？',
        'userModelConfigured' => 'User モデルの設定が完了しました。',
        'userModelFailed' => 'User モデルを自動で設定できませんでした: :error',
        'success' => 'Escalated のインストールが完了しました！',
        'nextSteps' => '次のステップ:',
        'stepTicketable' => 'User モデルに Ticketable インターフェースを実装してください:',
        'stepGates' => 'AuthServiceProvider で認可ゲートを定義してください:',
        'stepMigrate' => 'マイグレーションを実行してください:',
        'stepTailwind' => 'Tailwind のコンテンツ設定に Escalated のページを追加してください:',
        'stepInertia' => 'app.ts に Inertia のページリゾルバーとプラグインを追加してください:',
        'stepVisit' => '/support にアクセスしてカスタマーポータルを確認してください',
        'runningMigrations' => 'マイグレーションを実行中',
        'seedingPermissions' => '権限とロールをシード中',
        'runMigrationsConfirm' => '今すぐマイグレーションを実行し、デフォルトの権限をシードしますか？',
        'stepSeed' => 'デフォルトの権限とロールをシードする：',
    ],

];
