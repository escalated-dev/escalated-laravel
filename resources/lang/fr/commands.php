<?php

return [

    'install' => [
        'installing' => 'Installation d\'Escalated...',
        'publishingConfig' => 'Publication de la configuration',
        'publishingMigrations' => 'Publication des migrations',
        'migrationsAlreadyPublished' => ':count migration(s) Escalated déjà publiée(s) ; ignoré. Relancez avec --force pour les remplacer.',
        'publishingViews' => 'Publication des vues e-mail',
        'installingNpm' => 'Installation du paquet npm',
        'npmManual' => 'Impossible d\'installer le paquet npm automatiquement. Exécutez manuellement :',
        'userModelNotFound' => 'Impossible de trouver le modèle User. Vous devrez le configurer manuellement.',
        'userModelAlreadyConfigured' => 'Le modèle User implémente déjà Ticketable.',
        'userModelConfirm' => 'Souhaitez-vous configurer automatiquement votre modèle User pour implémenter Ticketable ?',
        'userModelConfigured' => 'Modèle User configuré avec succès.',
        'userModelFailed' => 'Impossible de configurer automatiquement le modèle User : :error',
        'success' => 'Escalated installé avec succès !',
        'nextSteps' => 'Prochaines étapes :',
        'stepTicketable' => 'Implémentez l\'interface Ticketable sur votre modèle User :',
        'stepGates' => 'Définissez les portes d\'autorisation dans votre AuthServiceProvider :',
        'stepMigrate' => 'Exécutez les migrations :',
        'stepTailwind' => 'Ajoutez les pages Escalated à la configuration de contenu Tailwind :',
        'stepInertia' => 'Ajoutez le résolveur de pages Inertia et le plugin dans votre app.ts :',
        'stepVisit' => 'Visitez /support pour voir le portail client',
        'runningMigrations' => 'Exécution des migrations',
        'seedingPermissions' => 'Création des permissions et rôles',
        'runMigrationsConfirm' => 'Exécuter les migrations et créer les permissions par défaut maintenant ?',
        'stepSeed' => 'Créer les permissions et rôles par défaut :',
    ],

];
