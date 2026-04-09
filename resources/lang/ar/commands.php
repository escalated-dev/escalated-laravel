<?php

return [

    'install' => [
        'installing' => 'جارٍ تثبيت Escalated...',
        'publishing_config' => 'نشر الإعدادات',
        'publishing_migrations' => 'نشر عمليات الترحيل',
        'publishing_views' => 'نشر قوالب البريد الإلكتروني',
        'installing_npm' => 'تثبيت حزمة npm',
        'npm_manual' => 'تعذّر تثبيت حزمة npm تلقائيًا. قم بتشغيل الأمر يدويًا:',
        'user_model_not_found' => 'تعذّر العثور على نموذج المستخدم. ستحتاج إلى ضبطه يدويًا.',
        'user_model_already_configured' => 'نموذج المستخدم ينفّذ بالفعل واجهة Ticketable.',
        'user_model_confirm' => 'هل تريد ضبط نموذج المستخدم تلقائيًا لتنفيذ واجهة Ticketable؟',
        'user_model_configured' => 'تم ضبط نموذج المستخدم بنجاح.',
        'user_model_failed' => 'تعذّر ضبط نموذج المستخدم تلقائيًا: :error',
        'success' => 'تم تثبيت Escalated بنجاح!',
        'next_steps' => 'الخطوات التالية:',
        'step_ticketable' => 'نفّذ واجهة Ticketable على نموذج المستخدم الخاص بك:',
        'step_gates' => 'حدّد بوابات التفويض في AuthServiceProvider الخاص بك:',
        'step_migrate' => 'شغّل عمليات الترحيل:',
        'step_tailwind' => 'أضف صفحات Escalated إلى إعدادات محتوى Tailwind الخاصة بك:',
        'step_inertia' => 'أضف محلل صفحات Inertia والإضافة في ملف app.ts الخاص بك:',
        'step_visit' => 'قم بزيارة /support لرؤية بوابة العملاء',
    ],

];
