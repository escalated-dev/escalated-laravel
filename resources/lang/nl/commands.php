<?php

return [

    'install' => [
        'installing' => 'Escalated wordt geïnstalleerd...',
        'publishingConfig' => 'Configuratie publiceren',
        'publishingMigrations' => 'Migraties publiceren',
        'migrationsAlreadyPublished' => ':count Escalated-migratie(s) al gepubliceerd; overgeslagen. Voer opnieuw uit met --force om ze te vervangen.',
        'publishingViews' => 'E-mailweergaven publiceren',
        'installingNpm' => 'npm-pakket installeren',
        'npmManual' => 'Kan het npm-pakket niet automatisch installeren. Voer handmatig uit:',
        'userModelNotFound' => 'Kan het User-model niet vinden. U moet het handmatig configureren.',
        'userModelAlreadyConfigured' => 'Het User-model implementeert al Ticketable.',
        'userModelConfirm' => 'Wilt u uw User-model automatisch configureren om Ticketable te implementeren?',
        'userModelConfigured' => 'User-model succesvol geconfigureerd.',
        'userModelFailed' => 'Kan het User-model niet automatisch configureren: :error',
        'success' => 'Escalated is succesvol geïnstalleerd!',
        'nextSteps' => 'Volgende stappen:',
        'stepTicketable' => 'Implementeer de Ticketable-interface op uw User-model:',
        'stepGates' => 'Definieer autorisatiegates in uw AuthServiceProvider:',
        'stepMigrate' => 'Voer migraties uit:',
        'stepTailwind' => 'Voeg Escalated-pagina\'s toe aan uw Tailwind-contentconfiguratie:',
        'stepInertia' => 'Voeg de Inertia-paginaresolver en -plugin toe in uw app.ts:',
        'stepVisit' => 'Bezoek /support om het klantenportaal te bekijken',
        'runningMigrations' => 'Migraties worden uitgevoerd',
        'seedingPermissions' => 'Permissies en rollen zaaien',
        'runMigrationsConfirm' => 'Migraties uitvoeren en standaardrechten nu aanmaken?',
        'stepSeed' => 'Standaardrechten en -rollen aanmaken:',
    ],

];
