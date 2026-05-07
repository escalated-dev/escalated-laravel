<?php

return [

    'install' => [
        'installing' => 'Instalowanie Escalated...',
        'publishing_config' => 'Publikowanie konfiguracji',
        'publishing_migrations' => 'Publikowanie migracji',
        'migrations_already_published' => 'Opublikowano już :count migracj(ę|e) pakietu Escalated; pomijam. Uruchom ponownie z --force, aby je zastąpić.',
        'publishing_views' => 'Publikowanie widoków e-mail',
        'installing_npm' => 'Instalowanie pakietu npm',
        'npm_manual' => 'Nie udało się automatycznie zainstalować pakietu npm. Uruchom ręcznie:',
        'user_model_not_found' => 'Nie znaleziono modelu User. Musisz skonfigurować go ręcznie.',
        'user_model_already_configured' => 'Model User już implementuje interfejs Ticketable.',
        'user_model_confirm' => 'Czy chcesz automatycznie skonfigurować model User do implementacji Ticketable?',
        'user_model_configured' => 'Model User został pomyślnie skonfigurowany.',
        'user_model_failed' => 'Nie udało się automatycznie skonfigurować modelu User: :error',
        'success' => 'Escalated został pomyślnie zainstalowany!',
        'next_steps' => 'Następne kroki:',
        'step_ticketable' => 'Zaimplementuj interfejs Ticketable w swoim modelu User:',
        'step_gates' => 'Zdefiniuj bramki autoryzacji w swoim AuthServiceProvider:',
        'step_migrate' => 'Uruchom migracje:',
        'step_tailwind' => 'Dodaj strony Escalated do konfiguracji zawartości Tailwind:',
        'step_inertia' => 'Dodaj resolver stron Inertia i wtyczkę w swoim pliku app.ts:',
        'step_visit' => 'Odwiedź /support, aby zobaczyć portal klienta',
        'running_migrations' => 'Uruchamianie migracji',
        'seeding_permissions' => 'Tworzenie uprawnień i ról',
        'run_migrations_confirm' => 'Uruchomić migracje i utworzyć domyślne uprawnienia teraz?',
        'step_seed' => 'Utwórz domyślne uprawnienia i role:',
    ],

];
