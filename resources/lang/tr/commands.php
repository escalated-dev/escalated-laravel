<?php

return [

    'install' => [
        'installing' => 'Escalated kuruluyor...',
        'publishing_config' => 'Yapılandırma yayınlanıyor',
        'publishing_migrations' => 'Geçişler yayınlanıyor',
        'migrations_already_published' => ':count Escalated geçişi zaten yayımlanmış; atlanıyor. Değiştirmek için --force ile yeniden çalıştırın.',
        'publishing_views' => 'E-posta görünümleri yayınlanıyor',
        'installing_npm' => 'npm paketi kuruluyor',
        'npm_manual' => 'npm paketi otomatik olarak kurulamadı. Manuel olarak çalıştırın:',
        'user_model_not_found' => 'User modeli bulunamadı. Manuel olarak yapılandırmanız gerekecek.',
        'user_model_already_configured' => 'User modeli zaten Ticketable arayüzünü uyguluyor.',
        'user_model_confirm' => 'User modelinizi otomatik olarak Ticketable arayüzünü uygulayacak şekilde yapılandırmak ister misiniz?',
        'user_model_configured' => 'User modeli başarıyla yapılandırıldı.',
        'user_model_failed' => 'User modeli otomatik olarak yapılandırılamadı: :error',
        'success' => 'Escalated başarıyla kuruldu!',
        'next_steps' => 'Sonraki adımlar:',
        'step_ticketable' => 'User modelinize Ticketable arayüzünü uygulayın:',
        'step_gates' => 'AuthServiceProvider içinde yetkilendirme kapılarını tanımlayın:',
        'step_migrate' => 'Geçişleri çalıştırın:',
        'step_tailwind' => 'Tailwind içerik yapılandırmanıza Escalated sayfalarını ekleyin:',
        'step_inertia' => 'app.ts dosyanıza Inertia sayfa çözümleyicisini ve eklentisini ekleyin:',
        'step_visit' => 'Müşteri portalını görmek için /support adresini ziyaret edin',
        'running_migrations' => 'Geçişler çalıştırılıyor',
        'seeding_permissions' => 'İzinler ve roller ekiliyor',
        'run_migrations_confirm' => 'Geçişleri şimdi çalıştır ve varsayılan izinleri oluştur?',
        'step_seed' => 'Varsayılan izinleri ve rolleri ekle:',
    ],

];
