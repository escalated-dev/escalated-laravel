<?php

return [

    'ticket' => [
        'reply_sent' => 'Yanıt gönderildi.',
        'note_added' => 'Not eklendi.',
        'assigned' => 'Talep atandı.',
        'status_updated' => 'Durum güncellendi.',
        'priority_updated' => 'Öncelik güncellendi.',
        'tags_updated' => 'Etiketler güncellendi.',
        'department_updated' => 'Departman güncellendi.',
        'macro_applied' => '":name" makrosu uygulandı.',
        'following' => 'Talebi takip ediyorsunuz.',
        'unfollowed' => 'Talep takibi bırakıldı.',
        'only_internal_notes_pinned' => 'Yalnızca dahili notlar sabitlenebilir.',
        'updated' => 'Talep güncellendi.',
        'created' => 'Talep başarıyla oluşturuldu.',
        'closed' => 'Talep kapatıldı.',
        'reopened' => 'Talep yeniden açıldı.',
        'customers_cannot_close' => 'Müşteriler talepleri kapatamaz.',
    ],

    'guest' => [
        'created' => 'Talep oluşturuldu. Talep durumunuzu kontrol etmek için bu bağlantıyı kaydedin.',
        'ticket_closed' => 'Bu talep kapatılmış.',
    ],

    'bulk' => [
        'updated' => ':count talep güncellendi.',
    ],

    'rating' => [
        'only_resolved_closed' => 'Yalnızca çözülmüş veya kapatılmış talepleri değerlendirebilirsiniz.',
        'already_rated' => 'Bu talep zaten değerlendirilmiş.',
        'thanks' => 'Geri bildiriminiz için teşekkürler!',
    ],

    'canned_response' => [
        'created' => 'Hazır yanıt oluşturuldu.',
        'updated' => 'Hazır yanıt güncellendi.',
        'deleted' => 'Hazır yanıt silindi.',
    ],

    'department' => [
        'created' => 'Departman oluşturuldu.',
        'updated' => 'Departman güncellendi.',
        'deleted' => 'Departman silindi.',
    ],

    'tag' => [
        'created' => 'Etiket oluşturuldu.',
        'updated' => 'Etiket güncellendi.',
        'deleted' => 'Etiket silindi.',
    ],

    'macro' => [
        'created' => 'Makro oluşturuldu.',
        'updated' => 'Makro güncellendi.',
        'deleted' => 'Makro silindi.',
    ],

    'escalation_rule' => [
        'created' => 'Kural oluşturuldu.',
        'updated' => 'Kural güncellendi.',
        'deleted' => 'Kural silindi.',
    ],

    'sla_policy' => [
        'created' => 'SLA politikası oluşturuldu.',
        'updated' => 'SLA politikası güncellendi.',
        'deleted' => 'SLA politikası silindi.',
    ],

    'settings' => [
        'updated' => 'Ayarlar güncellendi.',
    ],

    'plugin' => [
        'uploaded' => 'Eklenti başarıyla yüklendi. Artık etkinleştirebilirsiniz.',
        'upload_failed' => 'Eklenti yüklenemedi: :error',
        'activated' => 'Eklenti başarıyla etkinleştirildi.',
        'activate_failed' => 'Eklenti etkinleştirilemedi: :error',
        'deactivated' => 'Eklenti başarıyla devre dışı bırakıldı.',
        'deactivate_failed' => 'Eklenti devre dışı bırakılamadı: :error',
        'composer_delete' => 'Composer eklentileri silinemez. Paketi Composer üzerinden kaldırın.',
        'deleted' => 'Eklenti başarıyla silindi.',
        'delete_failed' => 'Eklenti silinemedi: :error',
    ],

    'middleware' => [
        'not_admin' => 'Destek yöneticisi olarak yetkiniz yok.',
        'not_agent' => 'Destek temsilcisi olarak yetkiniz yok.',
    ],

    'inbound_email' => [
        'disabled' => 'Gelen e-posta devre dışı.',
        'unknown_adapter' => 'Bilinmeyen adaptör.',
        'invalid_signature' => 'Geçersiz imza.',
        'processing_failed' => 'İşleme başarısız oldu.',
    ],

];
