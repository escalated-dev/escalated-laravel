<?php

return [

    'install' => [
        'installing' => 'Установка Escalated...',
        'publishingConfig' => 'Публикация конфигурации',
        'publishingMigrations' => 'Публикация миграций',
        'migrationsAlreadyPublished' => 'Миграции Escalated уже опубликованы (:count); пропущено. Запустите снова с --force, чтобы заменить их.',
        'publishingViews' => 'Публикация шаблонов электронной почты',
        'installingNpm' => 'Установка npm-пакета',
        'npmManual' => 'Не удалось автоматически установить npm-пакет. Выполните вручную:',
        'userModelNotFound' => 'Не удалось найти модель User. Вам нужно настроить её вручную.',
        'userModelAlreadyConfigured' => 'Модель User уже реализует интерфейс Ticketable.',
        'userModelConfirm' => 'Хотите автоматически настроить модель User для реализации Ticketable?',
        'userModelConfigured' => 'Модель User успешно настроена.',
        'userModelFailed' => 'Не удалось автоматически настроить модель User: :error',
        'success' => 'Escalated успешно установлен!',
        'nextSteps' => 'Следующие шаги:',
        'stepTicketable' => 'Реализуйте интерфейс Ticketable в вашей модели User:',
        'stepGates' => 'Определите шлюзы авторизации в вашем AuthServiceProvider:',
        'stepMigrate' => 'Запустите миграции:',
        'stepTailwind' => 'Добавьте страницы Escalated в конфигурацию содержимого Tailwind:',
        'stepInertia' => 'Добавьте резолвер страниц Inertia и плагин в ваш app.ts:',
        'stepVisit' => 'Перейдите на /support, чтобы увидеть клиентский портал',
        'runningMigrations' => 'Выполнение миграций',
        'seedingPermissions' => 'Заполнение разрешений и ролей',
        'runMigrationsConfirm' => 'Выполнить миграции и заполнить стандартные разрешения сейчас?',
        'stepSeed' => 'Заполнить стандартные разрешения и роли:',
    ],

];
