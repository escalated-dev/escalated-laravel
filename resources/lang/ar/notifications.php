<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] تذكرة جديدة: :subject',
        'line1' => 'تم إنشاء تذكرة دعم جديدة.',
        'subject_line' => '**الموضوع:** :subject',
        'priority_line' => '**الأولوية:** :priority',
        'action' => 'عرض التذكرة',
        'closing' => 'شكرًا لاستخدامك نظام الدعم الخاص بنا.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] تم تعيين تذكرة لك',
        'line1' => 'تم تعيين تذكرة لك.',
        'subject_line' => '**الموضوع:** :subject',
        'priority_line' => '**الأولوية:** :priority',
        'action' => 'عرض التذكرة',
        'closing' => 'يرجى المراجعة والرد في أقرب وقت ممكن.',
    ],

    'ticket_reply' => [
        'subject' => 'رد: [:reference] :subject',
        'line1' => 'تمت إضافة رد جديد إلى تذكرتك.',
        'action' => 'عرض التذكرة',
        'closing' => 'شكرًا لاستخدامك نظام الدعم الخاص بنا.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] تم حل التذكرة',
        'line1' => 'تم حل تذكرة الدعم الخاصة بك.',
        'subject_line' => '**الموضوع:** :subject',
        'reopen_line' => 'إذا كنت بحاجة إلى مزيد من المساعدة، يمكنك إعادة فتح هذه التذكرة.',
        'action' => 'عرض التذكرة',
        'closing' => 'شكرًا لاستخدامك نظام الدعم الخاص بنا.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] تم تحديث الحالة: :status',
        'line1' => 'تم تحديث حالة تذكرتك.',
        'from_line' => '**من:** :status',
        'to_line' => '**إلى:** :status',
        'action' => 'عرض التذكرة',
        'closing' => 'شكرًا لاستخدامك نظام الدعم الخاص بنا.',
    ],

    'sla_breach' => [
        'subject' => '[انتهاك SLA] [:reference] تم انتهاك SLA :type',
        'type_first_response' => 'الرد الأول',
        'type_resolution' => 'الحل',
        'line1' => 'تم انتهاك SLA في التذكرة :reference.',
        'type_line' => '**النوع:** SLA :type',
        'subject_line' => '**الموضوع:** :subject',
        'priority_line' => '**الأولوية:** :priority',
        'action' => 'عرض التذكرة',
        'closing' => 'يتطلب الأمر اهتمامًا فوريًا.',
    ],

    'ticket_escalated' => [
        'subject' => '[تصعيد] [:reference] :subject',
        'line1' => 'تم تصعيد تذكرة.',
        'subject_line' => '**الموضوع:** :subject',
        'priority_line' => '**الأولوية:** :priority',
        'reason_line' => '**السبب:** :reason',
        'action' => 'عرض التذكرة',
        'closing' => 'يتطلب الأمر اهتمامًا فوريًا.',
    ],

];
