<?php

return [

    'install' => [
        'installing' => 'Instalando Escalated...',
        'publishingConfig' => 'Publicando configuración',
        'publishingMigrations' => 'Publicando migraciones',
        'migrationsAlreadyPublished' => ':count migración(es) de Escalated ya publicada(s); omitiendo. Vuelve a ejecutar con --force para reemplazarlas.',
        'publishingViews' => 'Publicando vistas de correo',
        'installingNpm' => 'Instalando paquete npm',
        'npmManual' => 'No se pudo instalar el paquete npm automáticamente. Ejecute manualmente:',
        'userModelNotFound' => 'No se pudo encontrar el modelo User. Deberá configurarlo manualmente.',
        'userModelAlreadyConfigured' => 'El modelo User ya implementa Ticketable.',
        'userModelConfirm' => '¿Desea configurar automáticamente su modelo User para implementar Ticketable?',
        'userModelConfigured' => 'Modelo User configurado exitosamente.',
        'userModelFailed' => 'No se pudo configurar automáticamente el modelo User: :error',
        'success' => '¡Escalated instalado exitosamente!',
        'nextSteps' => 'Próximos pasos:',
        'stepTicketable' => 'Implemente la interfaz Ticketable en su modelo User:',
        'stepGates' => 'Defina las puertas de autorización en su AuthServiceProvider:',
        'stepMigrate' => 'Ejecute las migraciones:',
        'stepTailwind' => 'Añada las páginas de Escalated a la configuración de contenido de Tailwind:',
        'stepInertia' => 'Añada el resolvedor de páginas Inertia y el plugin en su app.ts:',
        'stepVisit' => 'Visite /support para ver el portal del cliente',
        'runningMigrations' => 'Ejecutando migraciones',
        'seedingPermissions' => 'Sembrando permisos y roles',
        'runMigrationsConfirm' => '¿Ejecutar las migraciones y sembrar los permisos por defecto ahora?',
        'stepSeed' => 'Sembrar permisos y roles por defecto:',
    ],

];
