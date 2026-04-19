<?php

return [

    'install' => [
        'installing' => 'Установка Escalated...',
        'publishing_config' => 'Публикация конфигурации',
        'publishing_migrations' => 'Публикация миграций',
        'migrations_already_published' => 'Миграции Escalated уже опубликованы (:count); пропущено. Запустите снова с --force, чтобы заменить их.',
        'publishing_views' => 'Публикация шаблонов электронной почты',
        'installing_npm' => 'Установка npm-пакета',
        'npm_manual' => 'Не удалось автоматически установить npm-пакет. Выполните вручную:',
        'user_model_not_found' => 'Не удалось найти модель User. Вам нужно настроить её вручную.',
        'user_model_already_configured' => 'Модель User уже реализует интерфейс Ticketable.',
        'user_model_confirm' => 'Хотите автоматически настроить модель User для реализации Ticketable?',
        'user_model_configured' => 'Модель User успешно настроена.',
        'user_model_failed' => 'Не удалось автоматически настроить модель User: :error',
        'success' => 'Escalated успешно установлен!',
        'next_steps' => 'Следующие шаги:',
        'step_ticketable' => 'Реализуйте интерфейс Ticketable в вашей модели User:',
        'step_gates' => 'Определите шлюзы авторизации в вашем AuthServiceProvider:',
        'step_migrate' => 'Запустите миграции:',
        'step_tailwind' => 'Добавьте страницы Escalated в конфигурацию содержимого Tailwind:',
        'step_inertia' => 'Добавьте резолвер страниц Inertia и плагин в ваш app.ts:',
        'step_visit' => 'Перейдите на /support, чтобы увидеть клиентский портал',
    ],

];
