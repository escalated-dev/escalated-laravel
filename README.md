<p align="center"><sub>
  <a href="docs/translations/README.ar.md">العربية</a> ·
  <a href="docs/translations/README.de.md">Deutsch</a> ·
  <b>English</b> ·
  <a href="docs/translations/README.es.md">Español</a> ·
  <a href="docs/translations/README.fr.md">Français</a> ·
  <a href="docs/translations/README.it.md">Italiano</a> ·
  <a href="docs/translations/README.ja.md">日本語</a> ·
  <a href="docs/translations/README.ko.md">한국어</a> ·
  <a href="docs/translations/README.nl.md">Nederlands</a> ·
  <a href="docs/translations/README.pl.md">Polski</a> ·
  <a href="docs/translations/README.pt-BR.md">Português (BR)</a> ·
  <a href="docs/translations/README.ru.md">Русский</a> ·
  <a href="docs/translations/README.tr.md">Türkçe</a> ·
  <a href="docs/translations/README.zh-CN.md">简体中文</a>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  Add a complete helpdesk to your Laravel app.<br>
  Tickets, SLAs, escalation rules, an agent inbox and a customer portal, running on your own database.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Website</b></a> ·
  <a href="https://escalated.dev/docs"><b>Docs</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="CHANGELOG.md"><b>Changelog</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="The Escalated agent inbox: saved views with counts, SLA countdowns and priorities on every ticket" width="100%">
</picture>

## What it does

<table>
  <tr>
    <td width="40%">
      <h3>An inbox your agents can work in all day</h3>
      Conversation, internal notes, canned replies and SLA timers on one screen. Tickets also arrive by email through Mailgun, Postmark, SES or IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="A ticket open in the agent workspace with the reply composer">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="The admin reports page with response times, CSAT and SLA breaches">
      </picture>
    </td>
    <td width="40%">
      <h3>SLAs and escalation rules you can see</h3>
      Response and resolution targets per priority with business hours, rules that escalate or reassign when they slip, and reports on breaches.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>A customer portal that's already built</h3>
      Customers open tickets, reply and check status inside your app. Embed the widget on other sites with one script tag.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="The customer portal ticket list">
      </picture>
    </td>
  </tr>
</table>

## Features

<table>
<tr>
<td width="33%" valign="top">

**Tickets**
- Create, assign, reply, resolve, reopen
- Configurable status transitions
- Split a reply into a new ticket
- Snooze with automatic wake
- Tags, attachments, activity timeline

</td>
<td width="33%" valign="top">

**Agent workspace**
- Queue with filters and saved views
- Bulk actions and internal notes
- Canned responses
- Departments with round-robin assignment
- Real-time updates with polling fallback

</td>
<td width="33%" valign="top">

**SLAs and escalation**
- Response and resolution targets per priority
- Business hours
- Automatic breach detection
- Rules that escalate, reassign or notify

</td>
</tr>
<tr>
<td width="33%" valign="top">

**Customers**
- Self-service portal: open, reply, track
- Verified guest tickets with private links
- Embeddable widget, form or live chat
- Knowledge base, on or off

</td>
<td width="33%" valign="top">

**Email**
- Inbound via Mailgun, Postmark, SES, IMAP
- Per-event notifications and webhooks
- Correct threading in mail clients
- Branded templates

</td>
<td width="33%" valign="top">

**Admin and access**
- Departments, SLA policies, rules, reports
- Roles, permissions, two-factor, SSO
- Admin inside your own admin panel
- Agent API for creating tickets

</td>
</tr>
</table>

<p align="center"><sub><a href="docs/features.md">Every feature in detail</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

## Quick start

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Then visit `/support`. Needs PHP 8.2+ and Laravel 11.39 or newer. The [getting started guide](docs/getting-started.md) covers user model setup and access gates.

## Learn more

- [All features](docs/features.md) · [Getting started](docs/getting-started.md) · [Installation](docs/installation.md)
- [Frontend integration and theming](docs/frontend-integration.md) · [Customization](docs/customization.md)
- [Configuration, hosting modes, events and routes](docs/operations.md) · [Configuration reference](docs/configuration.md)
- [SLA policies](docs/sla-policies.md) · [Escalation rules](docs/escalation-rules.md) · [Ticket access and routing](docs/ticket-access-and-routing.md)
- [Inbound email](docs/inbound-email.md) · [Embedding the widget](docs/embedding-the-widget.md) · [Guest access](docs/guest-access.md)
- [Plugins](docs/plugin-sdk.md) · [Newsletters](docs/newsletters.md) · [Tenancy](docs/tenancy.md)

Escalated is also available for [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) and [Filament](https://github.com/escalated-dev/escalated-filament). The UI lives in [escalated](https://github.com/escalated-dev/escalated).

## License

MIT. Copyright (c) Escalated.dev. See [LICENSE](LICENSE).
