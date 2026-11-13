<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <a href="README.es.md">Español</a> ·
  <a href="README.fr.md">Français</a> ·
  <a href="README.it.md">Italiano</a> ·
  <a href="README.ja.md">日本語</a> ·
  <a href="README.ko.md">한국어</a> ·
  <a href="README.nl.md">Nederlands</a> ·
  <a href="README.pl.md">Polski</a> ·
  <a href="README.pt-BR.md">Português (BR)</a> ·
  <b>Русский</b> ·
  <a href="README.tr.md">Türkçe</a> ·
  <a href="README.zh-CN.md">简体中文</a>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  Добавьте полноценный хелпдеск в ваше приложение на Laravel.<br>
  Тикеты, SLA, правила эскалации, входящие для агентов и клиентский портал, на вашей собственной базе данных.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Сайт</b></a> ·
  <a href="https://escalated.dev/docs"><b>Документация</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Список изменений</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="Входящие агента в Escalated: сохранённые представления со счётчиками, обратный отсчёт SLA и приоритет у каждого тикета" width="100%">
</picture>

## Что он делает

<table>
  <tr>
    <td width="40%">
      <h3>Входящие, в которых агентам удобно работать весь день</h3>
      Переписка, внутренние заметки, шаблоны ответов и таймеры SLA на одном экране. Тикеты также приходят по электронной почте через Mailgun, Postmark, SES или IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Тикет, открытый в рабочем пространстве агента, с редактором ответа">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="Страница отчётов в админке со временем ответа, CSAT и нарушениями SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>SLA и правила эскалации у вас на виду</h3>
      Цели по времени ответа и решения для каждого приоритета с учётом рабочих часов, правила, которые эскалируют или переназначают тикет при отставании, и отчёты о нарушениях.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Готовый клиентский портал</h3>
      Клиенты создают тикеты, отвечают и проверяют статус прямо в вашем приложении. Виджет встраивается на другие сайты одним тегом script.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="Список тикетов в клиентском портале">
      </picture>
    </td>
  </tr>
</table>

## Быстрый старт

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Затем откройте `/support`. Требуются PHP 8.2+ и Laravel 11.39 или новее. В [руководстве по началу работы](../getting-started.md) описана настройка модели пользователя и гейтов доступа.

## Подробнее

- [Все возможности](../features.md) · [Начало работы](../getting-started.md) · [Установка](../installation.md)
- [Интеграция с фронтендом и темы](../frontend-integration.md) · [Кастомизация](../customization.md)
- [Конфигурация, режимы хостинга, события и маршруты](../operations.md) · [Справочник по конфигурации](../configuration.md)
- [Политики SLA](../sla-policies.md) · [Правила эскалации](../escalation-rules.md) · [Доступ к тикетам и маршрутизация](../ticket-access-and-routing.md)
- [Входящая почта](../inbound-email.md) · [Встраивание виджета](../embedding-the-widget.md) · [Гостевой доступ](../guest-access.md)
- [Плагины](../plugin-sdk.md) · [Рассылки](../newsletters.md) · [Мультитенантность](../tenancy.md)

Escalated также доступен для [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) и [Filament](https://github.com/escalated-dev/escalated-filament). Интерфейс находится в [escalated](https://github.com/escalated-dev/escalated).

## Лицензия

MIT. Copyright (c) Escalated.dev. См. [LICENSE](../../LICENSE).
