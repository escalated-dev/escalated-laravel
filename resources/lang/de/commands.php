<?php

return [

    'install' => [
        'installing' => 'Escalated wird installiert...',
        'publishingConfig' => 'Konfiguration wird veröffentlicht',
        'publishingMigrations' => 'Migrationen werden veröffentlicht',
        'migrationsAlreadyPublished' => ':count Escalated-Migration(en) bereits veröffentlicht; übersprungen. Mit --force neu veröffentlichen.',
        'publishingViews' => 'E-Mail-Ansichten werden veröffentlicht',
        'installingNpm' => 'npm-Paket wird installiert',
        'npmManual' => 'npm-Paket konnte nicht automatisch installiert werden. Führen Sie manuell aus:',
        'userModelNotFound' => 'User-Modell konnte nicht gefunden werden. Sie müssen es manuell konfigurieren.',
        'userModelAlreadyConfigured' => 'User-Modell implementiert bereits Ticketable.',
        'userModelConfirm' => 'Möchten Sie Ihr User-Modell automatisch für Ticketable konfigurieren?',
        'userModelConfigured' => 'User-Modell erfolgreich konfiguriert.',
        'userModelFailed' => 'User-Modell konnte nicht automatisch konfiguriert werden: :error',
        'success' => 'Escalated erfolgreich installiert!',
        'nextSteps' => 'Nächste Schritte:',
        'stepTicketable' => 'Implementieren Sie das Ticketable-Interface in Ihrem User-Modell:',
        'stepGates' => 'Definieren Sie Autorisierungs-Gates in Ihrem AuthServiceProvider:',
        'stepMigrate' => 'Führen Sie die Migrationen aus:',
        'stepTailwind' => 'Fügen Sie Escalated-Seiten zur Tailwind-Inhaltskonfiguration hinzu:',
        'stepInertia' => 'Fügen Sie den Inertia-Seitenresolver und das Plugin in Ihrer app.ts hinzu:',
        'stepVisit' => 'Besuchen Sie /support, um das Kundenportal zu sehen',
        'runningMigrations' => 'Migrationen werden ausgeführt',
        'seedingPermissions' => 'Berechtigungen und Rollen werden eingerichtet',
        'runMigrationsConfirm' => 'Migrationen jetzt ausführen und Standardberechtigungen anlegen?',
        'stepSeed' => 'Standardberechtigungen und -rollen anlegen:',
    ],

];
