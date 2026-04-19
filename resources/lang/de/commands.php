<?php

return [

    'install' => [
        'installing' => 'Escalated wird installiert...',
        'publishing_config' => 'Konfiguration wird veröffentlicht',
        'publishing_migrations' => 'Migrationen werden veröffentlicht',
        'migrations_already_published' => ':count Escalated-Migration(en) bereits veröffentlicht; übersprungen. Mit --force neu veröffentlichen.',
        'publishing_views' => 'E-Mail-Ansichten werden veröffentlicht',
        'installing_npm' => 'npm-Paket wird installiert',
        'npm_manual' => 'npm-Paket konnte nicht automatisch installiert werden. Führen Sie manuell aus:',
        'user_model_not_found' => 'User-Modell konnte nicht gefunden werden. Sie müssen es manuell konfigurieren.',
        'user_model_already_configured' => 'User-Modell implementiert bereits Ticketable.',
        'user_model_confirm' => 'Möchten Sie Ihr User-Modell automatisch für Ticketable konfigurieren?',
        'user_model_configured' => 'User-Modell erfolgreich konfiguriert.',
        'user_model_failed' => 'User-Modell konnte nicht automatisch konfiguriert werden: :error',
        'success' => 'Escalated erfolgreich installiert!',
        'next_steps' => 'Nächste Schritte:',
        'step_ticketable' => 'Implementieren Sie das Ticketable-Interface in Ihrem User-Modell:',
        'step_gates' => 'Definieren Sie Autorisierungs-Gates in Ihrem AuthServiceProvider:',
        'step_migrate' => 'Führen Sie die Migrationen aus:',
        'step_tailwind' => 'Fügen Sie Escalated-Seiten zur Tailwind-Inhaltskonfiguration hinzu:',
        'step_inertia' => 'Fügen Sie den Inertia-Seitenresolver und das Plugin in Ihrer app.ts hinzu:',
        'step_visit' => 'Besuchen Sie /support, um das Kundenportal zu sehen',
    ],

];
