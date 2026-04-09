<?php

return [

    'install' => [
        'installing' => 'Escalated wordt geïnstalleerd...',
        'publishing_config' => 'Configuratie publiceren',
        'publishing_migrations' => 'Migraties publiceren',
        'publishing_views' => 'E-mailweergaven publiceren',
        'installing_npm' => 'npm-pakket installeren',
        'npm_manual' => 'Kan het npm-pakket niet automatisch installeren. Voer handmatig uit:',
        'user_model_not_found' => 'Kan het User-model niet vinden. U moet het handmatig configureren.',
        'user_model_already_configured' => 'Het User-model implementeert al Ticketable.',
        'user_model_confirm' => 'Wilt u uw User-model automatisch configureren om Ticketable te implementeren?',
        'user_model_configured' => 'User-model succesvol geconfigureerd.',
        'user_model_failed' => 'Kan het User-model niet automatisch configureren: :error',
        'success' => 'Escalated is succesvol geïnstalleerd!',
        'next_steps' => 'Volgende stappen:',
        'step_ticketable' => 'Implementeer de Ticketable-interface op uw User-model:',
        'step_gates' => 'Definieer autorisatiegates in uw AuthServiceProvider:',
        'step_migrate' => 'Voer migraties uit:',
        'step_tailwind' => 'Voeg Escalated-pagina\'s toe aan uw Tailwind-contentconfiguratie:',
        'step_inertia' => 'Voeg de Inertia-paginaresolver en -plugin toe in uw app.ts:',
        'step_visit' => 'Bezoek /support om het klantenportaal te bekijken',
    ],

];
