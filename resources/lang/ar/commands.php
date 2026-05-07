<?php

return [

    'install' => [
        'installing' => 'جارٍ تثبيت Escalated...',
        'publishingConfig' => 'نشر الإعدادات',
        'publishingMigrations' => 'نشر عمليات الترحيل',
        'migrationsAlreadyPublished' => ':count ملف ترحيل من Escalated منشور بالفعل؛ تم التخطي. أعد التشغيل مع --force لاستبدالها.',
        'publishingViews' => 'نشر قوالب البريد الإلكتروني',
        'installingNpm' => 'تثبيت حزمة npm',
        'npmManual' => 'تعذّر تثبيت حزمة npm تلقائيًا. قم بتشغيل الأمر يدويًا:',
        'userModelNotFound' => 'تعذّر العثور على نموذج المستخدم. ستحتاج إلى ضبطه يدويًا.',
        'userModelAlreadyConfigured' => 'نموذج المستخدم ينفّذ بالفعل واجهة Ticketable.',
        'userModelConfirm' => 'هل تريد ضبط نموذج المستخدم تلقائيًا لتنفيذ واجهة Ticketable؟',
        'userModelConfigured' => 'تم ضبط نموذج المستخدم بنجاح.',
        'userModelFailed' => 'تعذّر ضبط نموذج المستخدم تلقائيًا: :error',
        'success' => 'تم تثبيت Escalated بنجاح!',
        'nextSteps' => 'الخطوات التالية:',
        'stepTicketable' => 'نفّذ واجهة Ticketable على نموذج المستخدم الخاص بك:',
        'stepGates' => 'حدّد بوابات التفويض في AuthServiceProvider الخاص بك:',
        'stepMigrate' => 'شغّل عمليات الترحيل:',
        'stepTailwind' => 'أضف صفحات Escalated إلى إعدادات محتوى Tailwind الخاصة بك:',
        'stepInertia' => 'أضف محلل صفحات Inertia والإضافة في ملف app.ts الخاص بك:',
        'stepVisit' => 'قم بزيارة /support لرؤية بوابة العملاء',
        'runningMigrations' => 'تشغيل الترحيلات',
        'seedingPermissions' => 'زرع الأذونات والأدوار',
        'runMigrationsConfirm' => 'هل تريد تشغيل الترحيلات وزرع الأذونات الافتراضية الآن؟',
        'stepSeed' => 'زرع الأذونات والأدوار الافتراضية:',
    ],

];
