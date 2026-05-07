<?php

return [

    'install' => [
        'installing' => 'Installing Escalated...',
        'publishingConfig' => 'Publishing configuration',
        'publishingMigrations' => 'Publishing migrations',
        'migrationsAlreadyPublished' => ':count Escalated migration(s) already published; skipping. Re-run with --force to replace them.',
        'publishingViews' => 'Publishing email views',
        'installingNpm' => 'Installing npm package',
        'npmManual' => 'Could not install npm package automatically. Run manually:',
        'userModelNotFound' => 'Could not locate User model. You will need to configure it manually.',
        'userModelAlreadyConfigured' => 'User model already implements Ticketable.',
        'userModelConfirm' => 'Would you like to automatically configure your User model to implement Ticketable?',
        'userModelConfigured' => 'User model configured successfully.',
        'userModelFailed' => 'Could not automatically configure User model: :error',
        'success' => 'Escalated installed successfully!',
        'nextSteps' => 'Next steps:',
        'stepTicketable' => 'Implement the Ticketable interface on your User model:',
        'stepGates' => 'Define authorization gates in your AuthServiceProvider:',
        'stepMigrate' => 'Run migrations:',
        'stepTailwind' => 'Add Escalated pages to your Tailwind content config:',
        'stepInertia' => 'Add the Inertia page resolver and plugin in your app.ts:',
        'stepVisit' => 'Visit /support to see the customer portal',
        'runningMigrations' => 'Running migrations',
        'seedingPermissions' => 'Seeding permissions and roles',
        'runMigrationsConfirm' => 'Run migrations and seed default permissions now?',
        'stepSeed' => 'Seed default permissions and roles:',
    ],

];
