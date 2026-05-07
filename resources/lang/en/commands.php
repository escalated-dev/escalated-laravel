<?php

return [

    'install' => [
        'installing' => 'Installing Escalated...',
        'publishing_config' => 'Publishing configuration',
        'publishing_migrations' => 'Publishing migrations',
        'migrations_already_published' => ':count Escalated migration(s) already published; skipping. Re-run with --force to replace them.',
        'publishing_views' => 'Publishing email views',
        'installing_npm' => 'Installing npm package',
        'npm_manual' => 'Could not install npm package automatically. Run manually:',
        'user_model_not_found' => 'Could not locate User model. You will need to configure it manually.',
        'user_model_already_configured' => 'User model already implements Ticketable.',
        'user_model_confirm' => 'Would you like to automatically configure your User model to implement Ticketable?',
        'user_model_configured' => 'User model configured successfully.',
        'user_model_failed' => 'Could not automatically configure User model: :error',
        'success' => 'Escalated installed successfully!',
        'next_steps' => 'Next steps:',
        'step_ticketable' => 'Implement the Ticketable interface on your User model:',
        'step_gates' => 'Define authorization gates in your AuthServiceProvider:',
        'step_migrate' => 'Run migrations:',
        'step_tailwind' => 'Add Escalated pages to your Tailwind content config:',
        'step_inertia' => 'Add the Inertia page resolver and plugin in your app.ts:',
        'step_visit' => 'Visit /support to see the customer portal',
        'running_migrations' => 'Running migrations',
        'seeding_permissions' => 'Seeding permissions and roles',
        'run_migrations_confirm' => 'Run migrations and seed default permissions now?',
        'step_seed' => 'Seed default permissions and roles:',
    ],

];
