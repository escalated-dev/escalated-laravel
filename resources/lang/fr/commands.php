<?php

return [

    'install' => [
        'installing' => 'Installation d\'Escalated...',
        'publishing_config' => 'Publication de la configuration',
        'publishing_migrations' => 'Publication des migrations',
        'migrations_already_published' => ':count migration(s) Escalated déjà publiée(s) ; ignoré. Relancez avec --force pour les remplacer.',
        'publishing_views' => 'Publication des vues e-mail',
        'installing_npm' => 'Installation du paquet npm',
        'npm_manual' => 'Impossible d\'installer le paquet npm automatiquement. Exécutez manuellement :',
        'user_model_not_found' => 'Impossible de trouver le modèle User. Vous devrez le configurer manuellement.',
        'user_model_already_configured' => 'Le modèle User implémente déjà Ticketable.',
        'user_model_confirm' => 'Souhaitez-vous configurer automatiquement votre modèle User pour implémenter Ticketable ?',
        'user_model_configured' => 'Modèle User configuré avec succès.',
        'user_model_failed' => 'Impossible de configurer automatiquement le modèle User : :error',
        'success' => 'Escalated installé avec succès !',
        'next_steps' => 'Prochaines étapes :',
        'step_ticketable' => 'Implémentez l\'interface Ticketable sur votre modèle User :',
        'step_gates' => 'Définissez les portes d\'autorisation dans votre AuthServiceProvider :',
        'step_migrate' => 'Exécutez les migrations :',
        'step_tailwind' => 'Ajoutez les pages Escalated à la configuration de contenu Tailwind :',
        'step_inertia' => 'Ajoutez le résolveur de pages Inertia et le plugin dans votre app.ts :',
        'step_visit' => 'Visitez /support pour voir le portail client',
        'running_migrations' => 'Exécution des migrations',
        'seeding_permissions' => 'Création des permissions et rôles',
        'run_migrations_confirm' => 'Exécuter les migrations et créer les permissions par défaut maintenant ?',
        'step_seed' => 'Créer les permissions et rôles par défaut :',
    ],

];
