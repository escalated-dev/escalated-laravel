<?php

return [

    'install' => [
        'installing' => 'Installing Escalated...',
        'publishing_config' => 'Publishing configuration',
        'publishing_migrations' => 'Publishing migrations',
        'publishing_views' => 'Publishing email views',
        'installing_npm' => 'Installing npm package',
        'npm_manual' => 'Could not install npm package automatically. Run manually:',
        'success' => 'Escalated installed successfully!',
        'next_steps' => 'Next steps:',
        'step1' => '1. Implement the Ticketable interface on your User model:',
        'step2' => '2. Define authorization gates in your AuthServiceProvider:',
        'step3' => '3. Run migrations:',
        'step4' => '4. Add Escalated pages to your Tailwind content config:',
        'step5' => '5. Add the Inertia page resolver and plugin in your app.ts:',
        'step6' => '6. Visit /support to see the customer portal',
    ],

];
