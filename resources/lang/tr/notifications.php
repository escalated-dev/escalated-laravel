<?php

return [

    'new_ticket' => [
        'subject' => '[:reference] Yeni talep: :subject',
        'line1' => 'Yeni bir destek talebi oluşturuldu.',
        'subject_line' => '**Konu:** :subject',
        'priority_line' => '**Öncelik:** :priority',
        'action' => 'Talebi görüntüle',
        'closing' => 'Destek sistemimizi kullandığınız için teşekkürler.',
    ],

    'ticket_assigned' => [
        'subject' => '[:reference] Size bir talep atandı',
        'line1' => 'Bir talep size atandı.',
        'subject_line' => '**Konu:** :subject',
        'priority_line' => '**Öncelik:** :priority',
        'action' => 'Talebi görüntüle',
        'closing' => 'Lütfen en kısa sürede inceleyin ve yanıtlayın.',
    ],

    'ticket_reply' => [
        'subject' => 'Re: [:reference] :subject',
        'line1' => 'Talebinize yeni bir yanıt eklendi.',
        'action' => 'Talebi görüntüle',
        'closing' => 'Destek sistemimizi kullandığınız için teşekkürler.',
    ],

    'ticket_resolved' => [
        'subject' => '[:reference] Talep çözüldü',
        'line1' => 'Destek talebiniz çözüldü.',
        'subject_line' => '**Konu:** :subject',
        'reopen_line' => 'Daha fazla yardıma ihtiyacınız varsa bu talebi yeniden açabilirsiniz.',
        'action' => 'Talebi görüntüle',
        'closing' => 'Destek sistemimizi kullandığınız için teşekkürler.',
    ],

    'ticket_status_changed' => [
        'subject' => '[:reference] Durum güncellendi: :status',
        'line1' => 'Talebinizin durumu güncellendi.',
        'from_line' => '**Önceki:** :status',
        'to_line' => '**Yeni:** :status',
        'action' => 'Talebi görüntüle',
        'closing' => 'Destek sistemimizi kullandığınız için teşekkürler.',
    ],

    'sla_breach' => [
        'subject' => '[SLA İhlali] [:reference] :type SLA ihlal edildi',
        'type_first_response' => 'İlk yanıt',
        'type_resolution' => 'Çözüm',
        'line1' => ':reference talebinde SLA ihlal edildi.',
        'type_line' => '**Tür:** :type SLA',
        'subject_line' => '**Konu:** :subject',
        'priority_line' => '**Öncelik:** :priority',
        'action' => 'Talebi görüntüle',
        'closing' => 'Acil müdahale gereklidir.',
    ],

    'ticket_escalated' => [
        'subject' => '[Yükseltildi] [:reference] :subject',
        'line1' => 'Bir talep yükseltildi.',
        'subject_line' => '**Konu:** :subject',
        'priority_line' => '**Öncelik:** :priority',
        'reason_line' => '**Neden:** :reason',
        'action' => 'Talebi görüntüle',
        'closing' => 'Acil müdahale gereklidir.',
    ],

];
