<?php

return [

    'install' => [
        'installing' => '正在安装 Escalated...',
        'publishingConfig' => '正在发布配置文件',
        'publishingMigrations' => '正在发布数据库迁移',
        'migrationsAlreadyPublished' => '已发布 :count 个 Escalated 迁移文件；已跳过。使用 --force 重新运行可替换它们。',
        'publishingViews' => '正在发布邮件视图',
        'installingNpm' => '正在安装 npm 包',
        'npmManual' => '无法自动安装 npm 包。请手动运行：',
        'userModelNotFound' => '无法找到 User 模型。您需要手动配置。',
        'userModelAlreadyConfigured' => 'User 模型已实现 Ticketable 接口。',
        'userModelConfirm' => '是否自动配置 User 模型以实现 Ticketable 接口？',
        'userModelConfigured' => 'User 模型配置成功。',
        'userModelFailed' => '无法自动配置 User 模型：:error',
        'success' => 'Escalated 安装成功！',
        'nextSteps' => '后续步骤：',
        'stepTicketable' => '在 User 模型上实现 Ticketable 接口：',
        'stepGates' => '在 AuthServiceProvider 中定义授权门：',
        'stepMigrate' => '运行数据库迁移：',
        'stepTailwind' => '将 Escalated 页面添加到 Tailwind 内容配置中：',
        'stepInertia' => '在 app.ts 中添加 Inertia 页面解析器和插件：',
        'stepVisit' => '访问 /support 查看客户门户',
        'runningMigrations' => '正在运行迁移',
        'seedingPermissions' => '正在初始化权限和角色',
        'runMigrationsConfirm' => '现在运行迁移并初始化默认权限？',
        'stepSeed' => '初始化默认权限和角色：',
    ],

];
