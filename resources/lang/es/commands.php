<?php

return [

    'install' => [
        'installing' => 'Instalando Escalated...',
        'publishing_config' => 'Publicando configuración',
        'publishing_migrations' => 'Publicando migraciones',
        'publishing_views' => 'Publicando vistas de correo',
        'installing_npm' => 'Instalando paquete npm',
        'npm_manual' => 'No se pudo instalar el paquete npm automáticamente. Ejecute manualmente:',
        'user_model_not_found' => 'No se pudo encontrar el modelo User. Deberá configurarlo manualmente.',
        'user_model_already_configured' => 'El modelo User ya implementa Ticketable.',
        'user_model_confirm' => '¿Desea configurar automáticamente su modelo User para implementar Ticketable?',
        'user_model_configured' => 'Modelo User configurado exitosamente.',
        'user_model_failed' => 'No se pudo configurar automáticamente el modelo User: :error',
        'success' => '¡Escalated instalado exitosamente!',
        'next_steps' => 'Próximos pasos:',
        'step_ticketable' => 'Implemente la interfaz Ticketable en su modelo User:',
        'step_gates' => 'Defina las puertas de autorización en su AuthServiceProvider:',
        'step_migrate' => 'Ejecute las migraciones:',
        'step_tailwind' => 'Añada las páginas de Escalated a la configuración de contenido de Tailwind:',
        'step_inertia' => 'Añada el resolvedor de páginas Inertia y el plugin en su app.ts:',
        'step_visit' => 'Visite /support para ver el portal del cliente',
    ],

];
