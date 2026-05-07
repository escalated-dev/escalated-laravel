<?php

return [

    'install' => [
        'installing' => 'Installazione di Escalated...',
        'publishingConfig' => 'Pubblicazione della configurazione',
        'publishingMigrations' => 'Pubblicazione delle migrazioni',
        'migrationsAlreadyPublished' => ':count migrazione(i) di Escalated già pubblicata(e); saltata. Esegui di nuovo con --force per sostituirle.',
        'publishingViews' => 'Pubblicazione delle viste email',
        'installingNpm' => 'Installazione del pacchetto npm',
        'npmManual' => 'Impossibile installare automaticamente il pacchetto npm. Esegui manualmente:',
        'userModelNotFound' => 'Impossibile trovare il modello User. Dovrai configurarlo manualmente.',
        'userModelAlreadyConfigured' => 'Il modello User implementa già Ticketable.',
        'userModelConfirm' => 'Vuoi configurare automaticamente il modello User per implementare Ticketable?',
        'userModelConfigured' => 'Modello User configurato con successo.',
        'userModelFailed' => 'Impossibile configurare automaticamente il modello User: :error',
        'success' => 'Escalated installato con successo!',
        'nextSteps' => 'Prossimi passi:',
        'stepTicketable' => "Implementa l'interfaccia Ticketable sul tuo modello User:",
        'stepGates' => 'Definisci i gate di autorizzazione nel tuo AuthServiceProvider:',
        'stepMigrate' => 'Esegui le migrazioni:',
        'stepTailwind' => 'Aggiungi le pagine Escalated alla configurazione dei contenuti Tailwind:',
        'stepInertia' => 'Aggiungi il resolver delle pagine Inertia e il plugin nel tuo app.ts:',
        'stepVisit' => 'Visita /support per vedere il portale clienti',
        'runningMigrations' => 'Esecuzione delle migrazioni',
        'seedingPermissions' => 'Inserimento permessi e ruoli',
        'runMigrationsConfirm' => 'Eseguire ora le migrazioni e creare i permessi predefiniti?',
        'stepSeed' => 'Inserisci i permessi e ruoli predefiniti:',
    ],

];
