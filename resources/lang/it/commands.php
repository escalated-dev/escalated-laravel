<?php

return [

    'install' => [
        'installing' => 'Installazione di Escalated...',
        'publishing_config' => 'Pubblicazione della configurazione',
        'publishing_migrations' => 'Pubblicazione delle migrazioni',
        'publishing_views' => 'Pubblicazione delle viste email',
        'installing_npm' => 'Installazione del pacchetto npm',
        'npm_manual' => 'Impossibile installare automaticamente il pacchetto npm. Esegui manualmente:',
        'user_model_not_found' => 'Impossibile trovare il modello User. Dovrai configurarlo manualmente.',
        'user_model_already_configured' => 'Il modello User implementa già Ticketable.',
        'user_model_confirm' => 'Vuoi configurare automaticamente il modello User per implementare Ticketable?',
        'user_model_configured' => 'Modello User configurato con successo.',
        'user_model_failed' => 'Impossibile configurare automaticamente il modello User: :error',
        'success' => 'Escalated installato con successo!',
        'next_steps' => 'Prossimi passi:',
        'step_ticketable' => "Implementa l'interfaccia Ticketable sul tuo modello User:",
        'step_gates' => 'Definisci i gate di autorizzazione nel tuo AuthServiceProvider:',
        'step_migrate' => 'Esegui le migrazioni:',
        'step_tailwind' => 'Aggiungi le pagine Escalated alla configurazione dei contenuti Tailwind:',
        'step_inertia' => 'Aggiungi il resolver delle pagine Inertia e il plugin nel tuo app.ts:',
        'step_visit' => 'Visita /support per vedere il portale clienti',
    ],

];
