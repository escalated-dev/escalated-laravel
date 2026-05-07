<?php

return [

    'install' => [
        'installing' => 'Instalowanie Escalated...',
        'publishingConfig' => 'Publikowanie konfiguracji',
        'publishingMigrations' => 'Publikowanie migracji',
        'migrationsAlreadyPublished' => 'Opublikowano już :count migracj(ę|e) pakietu Escalated; pomijam. Uruchom ponownie z --force, aby je zastąpić.',
        'publishingViews' => 'Publikowanie widoków e-mail',
        'installingNpm' => 'Instalowanie pakietu npm',
        'npmManual' => 'Nie udało się automatycznie zainstalować pakietu npm. Uruchom ręcznie:',
        'userModelNotFound' => 'Nie znaleziono modelu User. Musisz skonfigurować go ręcznie.',
        'userModelAlreadyConfigured' => 'Model User już implementuje interfejs Ticketable.',
        'userModelConfirm' => 'Czy chcesz automatycznie skonfigurować model User do implementacji Ticketable?',
        'userModelConfigured' => 'Model User został pomyślnie skonfigurowany.',
        'userModelFailed' => 'Nie udało się automatycznie skonfigurować modelu User: :error',
        'success' => 'Escalated został pomyślnie zainstalowany!',
        'nextSteps' => 'Następne kroki:',
        'stepTicketable' => 'Zaimplementuj interfejs Ticketable w swoim modelu User:',
        'stepGates' => 'Zdefiniuj bramki autoryzacji w swoim AuthServiceProvider:',
        'stepMigrate' => 'Uruchom migracje:',
        'stepTailwind' => 'Dodaj strony Escalated do konfiguracji zawartości Tailwind:',
        'stepInertia' => 'Dodaj resolver stron Inertia i wtyczkę w swoim pliku app.ts:',
        'stepVisit' => 'Odwiedź /support, aby zobaczyć portal klienta',
        'runningMigrations' => 'Uruchamianie migracji',
        'seedingPermissions' => 'Tworzenie uprawnień i ról',
        'runMigrationsConfirm' => 'Uruchomić migracje i utworzyć domyślne uprawnienia teraz?',
        'stepSeed' => 'Utwórz domyślne uprawnienia i role:',
    ],

];
