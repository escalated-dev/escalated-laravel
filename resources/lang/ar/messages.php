<?php

return [

    'ticket' => [
        'reply_sent' => 'تم إرسال الرد.',
        'note_added' => 'تمت إضافة الملاحظة.',
        'assigned' => 'تم تعيين التذكرة.',
        'status_updated' => 'تم تحديث الحالة.',
        'priority_updated' => 'تم تحديث الأولوية.',
        'tags_updated' => 'تم تحديث الوسوم.',
        'department_updated' => 'تم تحديث القسم.',
        'macro_applied' => 'تم تطبيق الماكرو ":name".',
        'following' => 'أنت تتابع التذكرة.',
        'unfollowed' => 'تم إلغاء متابعة التذكرة.',
        'only_internal_notes_pinned' => 'يمكن تثبيت الملاحظات الداخلية فقط.',
        'updated' => 'تم تحديث التذكرة.',
        'created' => 'تم إنشاء التذكرة بنجاح.',
        'closed' => 'تم إغلاق التذكرة.',
        'reopened' => 'تم إعادة فتح التذكرة.',
        'customers_cannot_close' => 'لا يمكن للعملاء إغلاق التذاكر.',
    ],

    'guest' => [
        'created' => 'تم إنشاء التذكرة. احفظ هذا الرابط للتحقق من حالة تذكرتك.',
        'ticket_closed' => 'هذه التذكرة مغلقة.',
    ],

    'bulk' => [
        'updated' => 'تم تحديث :count تذكرة (تذاكر).',
    ],

    'rating' => [
        'only_resolved_closed' => 'يمكنك تقييم التذاكر المحلولة أو المغلقة فقط.',
        'already_rated' => 'تم تقييم هذه التذكرة بالفعل.',
        'thanks' => 'شكرًا لك على ملاحظاتك!',
    ],

    'canned_response' => [
        'created' => 'تم إنشاء الرد الجاهز.',
        'updated' => 'تم تحديث الرد الجاهز.',
        'deleted' => 'تم حذف الرد الجاهز.',
    ],

    'department' => [
        'created' => 'تم إنشاء القسم.',
        'updated' => 'تم تحديث القسم.',
        'deleted' => 'تم حذف القسم.',
    ],

    'tag' => [
        'created' => 'تم إنشاء الوسم.',
        'updated' => 'تم تحديث الوسم.',
        'deleted' => 'تم حذف الوسم.',
    ],

    'macro' => [
        'created' => 'تم إنشاء الماكرو.',
        'updated' => 'تم تحديث الماكرو.',
        'deleted' => 'تم حذف الماكرو.',
    ],

    'escalation_rule' => [
        'created' => 'تم إنشاء القاعدة.',
        'updated' => 'تم تحديث القاعدة.',
        'deleted' => 'تم حذف القاعدة.',
    ],

    'sla_policy' => [
        'created' => 'تم إنشاء سياسة SLA.',
        'updated' => 'تم تحديث سياسة SLA.',
        'deleted' => 'تم حذف سياسة SLA.',
    ],

    'settings' => [
        'updated' => 'تم تحديث الإعدادات.',
    ],

    'plugin' => [
        'uploaded' => 'تم رفع الإضافة بنجاح. يمكنك الآن تفعيلها.',
        'upload_failed' => 'فشل رفع الإضافة: :error',
        'activated' => 'تم تفعيل الإضافة بنجاح.',
        'activate_failed' => 'فشل تفعيل الإضافة: :error',
        'deactivated' => 'تم تعطيل الإضافة بنجاح.',
        'deactivate_failed' => 'فشل تعطيل الإضافة: :error',
        'composer_delete' => 'لا يمكن حذف إضافات Composer. قم بإزالة الحزمة عبر Composer بدلاً من ذلك.',
        'deleted' => 'تم حذف الإضافة بنجاح.',
        'delete_failed' => 'فشل حذف الإضافة: :error',
    ],

    'middleware' => [
        'not_admin' => 'ليس لديك صلاحية كمسؤول دعم.',
        'not_agent' => 'ليس لديك صلاحية كوكيل دعم.',
    ],

    'inbound_email' => [
        'disabled' => 'البريد الإلكتروني الوارد معطّل.',
        'unknown_adapter' => 'محوّل غير معروف.',
        'invalid_signature' => 'توقيع غير صالح.',
        'processing_failed' => 'فشلت المعالجة.',
    ],

];
