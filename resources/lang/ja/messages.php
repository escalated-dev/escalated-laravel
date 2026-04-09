<?php

return [

    'ticket' => [
        'reply_sent' => '返信を送信しました。',
        'note_added' => 'メモを追加しました。',
        'assigned' => 'チケットを割り当てました。',
        'status_updated' => 'ステータスを更新しました。',
        'priority_updated' => '優先度を更新しました。',
        'tags_updated' => 'タグを更新しました。',
        'department_updated' => '部門を更新しました。',
        'macro_applied' => 'マクロ「:name」を適用しました。',
        'following' => 'チケットをフォローしています。',
        'unfollowed' => 'チケットのフォローを解除しました。',
        'only_internal_notes_pinned' => '内部メモのみピン留めできます。',
        'updated' => 'チケットを更新しました。',
        'created' => 'チケットを作成しました。',
        'closed' => 'チケットをクローズしました。',
        'reopened' => 'チケットを再オープンしました。',
        'customers_cannot_close' => 'お客様はチケットをクローズできません。',
    ],

    'guest' => [
        'created' => 'チケットが作成されました。このリンクを保存してチケットの状況を確認してください。',
        'ticket_closed' => 'このチケットはクローズされています。',
    ],

    'bulk' => [
        'updated' => ':count 件のチケットを更新しました。',
    ],

    'rating' => [
        'only_resolved_closed' => '解決済みまたはクローズ済みのチケットのみ評価できます。',
        'already_rated' => 'このチケットはすでに評価済みです。',
        'thanks' => 'フィードバックありがとうございます！',
    ],

    'canned_response' => [
        'created' => '定型文を作成しました。',
        'updated' => '定型文を更新しました。',
        'deleted' => '定型文を削除しました。',
    ],

    'department' => [
        'created' => '部門を作成しました。',
        'updated' => '部門を更新しました。',
        'deleted' => '部門を削除しました。',
    ],

    'tag' => [
        'created' => 'タグを作成しました。',
        'updated' => 'タグを更新しました。',
        'deleted' => 'タグを削除しました。',
    ],

    'macro' => [
        'created' => 'マクロを作成しました。',
        'updated' => 'マクロを更新しました。',
        'deleted' => 'マクロを削除しました。',
    ],

    'escalation_rule' => [
        'created' => 'ルールを作成しました。',
        'updated' => 'ルールを更新しました。',
        'deleted' => 'ルールを削除しました。',
    ],

    'sla_policy' => [
        'created' => 'SLA ポリシーを作成しました。',
        'updated' => 'SLA ポリシーを更新しました。',
        'deleted' => 'SLA ポリシーを削除しました。',
    ],

    'settings' => [
        'updated' => '設定を更新しました。',
    ],

    'plugin' => [
        'uploaded' => 'プラグインのアップロードが完了しました。有効化できます。',
        'upload_failed' => 'プラグインのアップロードに失敗しました: :error',
        'activated' => 'プラグインを有効化しました。',
        'activate_failed' => 'プラグインの有効化に失敗しました: :error',
        'deactivated' => 'プラグインを無効化しました。',
        'deactivate_failed' => 'プラグインの無効化に失敗しました: :error',
        'composer_delete' => 'Composer プラグインは削除できません。代わりに Composer でパッケージを削除してください。',
        'deleted' => 'プラグインを削除しました。',
        'delete_failed' => 'プラグインの削除に失敗しました: :error',
    ],

    'middleware' => [
        'not_admin' => 'サポート管理者としての権限がありません。',
        'not_agent' => 'サポート担当者としての権限がありません。',
    ],

    'inbound_email' => [
        'disabled' => '受信メールは無効です。',
        'unknown_adapter' => '不明なアダプターです。',
        'invalid_signature' => '無効な署名です。',
        'processing_failed' => '処理に失敗しました。',
    ],

];
