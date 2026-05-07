<?php

return [

    'install' => [
        'installing' => 'Instalando o Escalated...',
        'publishingConfig' => 'Publicando configuração',
        'publishingMigrations' => 'Publicando migrações',
        'migrationsAlreadyPublished' => ':count migração(ões) do Escalated já publicada(s); ignorando. Execute novamente com --force para substituí-las.',
        'publishingViews' => 'Publicando visualizações de e-mail',
        'installingNpm' => 'Instalando pacote npm',
        'npmManual' => 'Não foi possível instalar o pacote npm automaticamente. Execute manualmente:',
        'userModelNotFound' => 'Não foi possível localizar o modelo User. Você precisará configurá-lo manualmente.',
        'userModelAlreadyConfigured' => 'O modelo User já implementa Ticketable.',
        'userModelConfirm' => 'Deseja configurar automaticamente o modelo User para implementar Ticketable?',
        'userModelConfigured' => 'Modelo User configurado com sucesso.',
        'userModelFailed' => 'Não foi possível configurar automaticamente o modelo User: :error',
        'success' => 'Escalated instalado com sucesso!',
        'nextSteps' => 'Próximos passos:',
        'stepTicketable' => 'Implemente a interface Ticketable no seu modelo User:',
        'stepGates' => 'Defina os gates de autorização no seu AuthServiceProvider:',
        'stepMigrate' => 'Execute as migrações:',
        'stepTailwind' => 'Adicione as páginas do Escalated à configuração de conteúdo do Tailwind:',
        'stepInertia' => 'Adicione o resolver de páginas e o plugin do Inertia no seu app.ts:',
        'stepVisit' => 'Acesse /support para ver o portal do cliente',
        'runningMigrations' => 'Executando migrações',
        'seedingPermissions' => 'Semeando permissões e funções',
        'runMigrationsConfirm' => 'Executar migrações e semear permissões padrão agora?',
        'stepSeed' => 'Semear permissões e funções padrão:',
    ],

];
