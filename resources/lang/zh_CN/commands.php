<?php

return [

    'install' => [
        'installing' => '正在安装 Escalated...',
        'publishing_config' => '正在发布配置文件',
        'publishing_migrations' => '正在发布数据库迁移',
        'migrations_already_published' => '已发布 :count 个 Escalated 迁移文件；已跳过。使用 --force 重新运行可替换它们。',
        'publishing_views' => '正在发布邮件视图',
        'installing_npm' => '正在安装 npm 包',
        'npm_manual' => '无法自动安装 npm 包。请手动运行：',
        'user_model_not_found' => '无法找到 User 模型。您需要手动配置。',
        'user_model_already_configured' => 'User 模型已实现 Ticketable 接口。',
        'user_model_confirm' => '是否自动配置 User 模型以实现 Ticketable 接口？',
        'user_model_configured' => 'User 模型配置成功。',
        'user_model_failed' => '无法自动配置 User 模型：:error',
        'success' => 'Escalated 安装成功！',
        'next_steps' => '后续步骤：',
        'step_ticketable' => '在 User 模型上实现 Ticketable 接口：',
        'step_gates' => '在 AuthServiceProvider 中定义授权门：',
        'step_migrate' => '运行数据库迁移：',
        'step_tailwind' => '将 Escalated 页面添加到 Tailwind 内容配置中：',
        'step_inertia' => '在 app.ts 中添加 Inertia 页面解析器和插件：',
        'step_visit' => '访问 /support 查看客户门户',
    ],

];
