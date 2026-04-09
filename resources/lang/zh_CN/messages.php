<?php

return [

    'ticket' => [
        'reply_sent' => '回复已发送。',
        'note_added' => '备注已添加。',
        'assigned' => '工单已分配。',
        'status_updated' => '状态已更新。',
        'priority_updated' => '优先级已更新。',
        'tags_updated' => '标签已更新。',
        'department_updated' => '部门已更新。',
        'macro_applied' => '宏":name"已应用。',
        'following' => '正在关注工单。',
        'unfollowed' => '已取消关注工单。',
        'only_internal_notes_pinned' => '仅内部备注可以置顶。',
        'updated' => '工单已更新。',
        'created' => '工单创建成功。',
        'closed' => '工单已关闭。',
        'reopened' => '工单已重新打开。',
        'customers_cannot_close' => '客户无法关闭工单。',
    ],

    'guest' => [
        'created' => '工单已创建。请保存此链接以查看工单状态。',
        'ticket_closed' => '此工单已关闭。',
    ],

    'bulk' => [
        'updated' => '已更新 :count 个工单。',
    ],

    'rating' => [
        'only_resolved_closed' => '您只能评价已解决或已关闭的工单。',
        'already_rated' => '此工单已被评价。',
        'thanks' => '感谢您的反馈！',
    ],

    'canned_response' => [
        'created' => '快捷回复已创建。',
        'updated' => '快捷回复已更新。',
        'deleted' => '快捷回复已删除。',
    ],

    'department' => [
        'created' => '部门已创建。',
        'updated' => '部门已更新。',
        'deleted' => '部门已删除。',
    ],

    'tag' => [
        'created' => '标签已创建。',
        'updated' => '标签已更新。',
        'deleted' => '标签已删除。',
    ],

    'macro' => [
        'created' => '宏已创建。',
        'updated' => '宏已更新。',
        'deleted' => '宏已删除。',
    ],

    'escalation_rule' => [
        'created' => '规则已创建。',
        'updated' => '规则已更新。',
        'deleted' => '规则已删除。',
    ],

    'sla_policy' => [
        'created' => 'SLA 策略已创建。',
        'updated' => 'SLA 策略已更新。',
        'deleted' => 'SLA 策略已删除。',
    ],

    'settings' => [
        'updated' => '设置已更新。',
    ],

    'plugin' => [
        'uploaded' => '插件上传成功。您现在可以激活它。',
        'upload_failed' => '插件上传失败：:error',
        'activated' => '插件激活成功。',
        'activate_failed' => '插件激活失败：:error',
        'deactivated' => '插件已停用。',
        'deactivate_failed' => '插件停用失败：:error',
        'composer_delete' => 'Composer 插件无法删除。请通过 Composer 移除该包。',
        'deleted' => '插件删除成功。',
        'delete_failed' => '插件删除失败：:error',
    ],

    'middleware' => [
        'not_admin' => '您没有支持管理员权限。',
        'not_agent' => '您没有支持客服权限。',
    ],

    'inbound_email' => [
        'disabled' => '入站邮件已禁用。',
        'unknown_adapter' => '未知的适配器。',
        'invalid_signature' => '无效的签名。',
        'processing_failed' => '处理失败。',
    ],

];
