<?php

return [

    'install' => [
        'installing' => 'Instalando o Escalated...',
        'publishing_config' => 'Publicando configuração',
        'publishing_migrations' => 'Publicando migrações',
        'migrations_already_published' => ':count migração(ões) do Escalated já publicada(s); ignorando. Execute novamente com --force para substituí-las.',
        'publishing_views' => 'Publicando visualizações de e-mail',
        'installing_npm' => 'Instalando pacote npm',
        'npm_manual' => 'Não foi possível instalar o pacote npm automaticamente. Execute manualmente:',
        'user_model_not_found' => 'Não foi possível localizar o modelo User. Você precisará configurá-lo manualmente.',
        'user_model_already_configured' => 'O modelo User já implementa Ticketable.',
        'user_model_confirm' => 'Deseja configurar automaticamente o modelo User para implementar Ticketable?',
        'user_model_configured' => 'Modelo User configurado com sucesso.',
        'user_model_failed' => 'Não foi possível configurar automaticamente o modelo User: :error',
        'success' => 'Escalated instalado com sucesso!',
        'next_steps' => 'Próximos passos:',
        'step_ticketable' => 'Implemente a interface Ticketable no seu modelo User:',
        'step_gates' => 'Defina os gates de autorização no seu AuthServiceProvider:',
        'step_migrate' => 'Execute as migrações:',
        'step_tailwind' => 'Adicione as páginas do Escalated à configuração de conteúdo do Tailwind:',
        'step_inertia' => 'Adicione o resolver de páginas e o plugin do Inertia no seu app.ts:',
        'step_visit' => 'Acesse /support para ver o portal do cliente',
        'running_migrations' => 'Executando migrações',
        'seeding_permissions' => 'Semeando permissões e funções',
        'run_migrations_confirm' => 'Executar migrações e semear permissões padrão agora?',
        'step_seed' => 'Semear permissões e funções padrão:',
    ],

];
