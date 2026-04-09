<?php

return [

    'status' => [
        'open' => 'مفتوح',
        'in_progress' => 'قيد التنفيذ',
        'waiting_on_customer' => 'بانتظار العميل',
        'waiting_on_agent' => 'بانتظار الوكيل',
        'escalated' => 'مُصعَّد',
        'resolved' => 'تم الحل',
        'closed' => 'مغلق',
        'snoozed' => 'مؤجَّل',
        'unsnoozed' => 'تم إلغاء التأجيل',
        'reopened' => 'أُعيد فتحه',
        'live' => 'مباشر',
    ],

    'priority' => [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'مرتفع',
        'urgent' => 'عاجل',
        'critical' => 'حرج',
    ],

    'activity_type' => [
        'status_changed' => 'تم تغيير الحالة',
        'assigned' => 'تم التعيين',
        'unassigned' => 'تم إلغاء التعيين',
        'priority_changed' => 'تم تغيير الأولوية',
        'tag_added' => 'تمت إضافة وسم',
        'tag_removed' => 'تمت إزالة وسم',
        'escalated' => 'تم التصعيد',
        'sla_breached' => 'تم انتهاك SLA',
        'replied' => 'تم الرد',
        'note_added' => 'تمت إضافة ملاحظة',
        'department_changed' => 'تم تغيير القسم',
        'reopened' => 'أُعيد فتحه',
        'resolved' => 'تم الحل',
        'closed' => 'مغلق',
        'snoozed' => 'مؤجَّل',
        'unsnoozed' => 'تم إلغاء التأجيل',
        'ticket_split' => 'تم تقسيم التذكرة',
    ],

];
