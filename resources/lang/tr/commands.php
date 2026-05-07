<?php

return [

    'install' => [
        'installing' => 'Escalated kuruluyor...',
        'publishingConfig' => 'Yapılandırma yayınlanıyor',
        'publishingMigrations' => 'Geçişler yayınlanıyor',
        'migrationsAlreadyPublished' => ':count Escalated geçişi zaten yayımlanmış; atlanıyor. Değiştirmek için --force ile yeniden çalıştırın.',
        'publishingViews' => 'E-posta görünümleri yayınlanıyor',
        'installingNpm' => 'npm paketi kuruluyor',
        'npmManual' => 'npm paketi otomatik olarak kurulamadı. Manuel olarak çalıştırın:',
        'userModelNotFound' => 'User modeli bulunamadı. Manuel olarak yapılandırmanız gerekecek.',
        'userModelAlreadyConfigured' => 'User modeli zaten Ticketable arayüzünü uyguluyor.',
        'userModelConfirm' => 'User modelinizi otomatik olarak Ticketable arayüzünü uygulayacak şekilde yapılandırmak ister misiniz?',
        'userModelConfigured' => 'User modeli başarıyla yapılandırıldı.',
        'userModelFailed' => 'User modeli otomatik olarak yapılandırılamadı: :error',
        'success' => 'Escalated başarıyla kuruldu!',
        'nextSteps' => 'Sonraki adımlar:',
        'stepTicketable' => 'User modelinize Ticketable arayüzünü uygulayın:',
        'stepGates' => 'AuthServiceProvider içinde yetkilendirme kapılarını tanımlayın:',
        'stepMigrate' => 'Geçişleri çalıştırın:',
        'stepTailwind' => 'Tailwind içerik yapılandırmanıza Escalated sayfalarını ekleyin:',
        'stepInertia' => 'app.ts dosyanıza Inertia sayfa çözümleyicisini ve eklentisini ekleyin:',
        'stepVisit' => 'Müşteri portalını görmek için /support adresini ziyaret edin',
        'runningMigrations' => 'Geçişler çalıştırılıyor',
        'seedingPermissions' => 'İzinler ve roller ekiliyor',
        'runMigrationsConfirm' => 'Geçişleri şimdi çalıştır ve varsayılan izinleri oluştur?',
        'stepSeed' => 'Varsayılan izinleri ve rolleri ekle:',
    ],

];
