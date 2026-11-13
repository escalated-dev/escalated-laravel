<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <a href="README.es.md">Español</a> ·
  <a href="README.fr.md">Français</a> ·
  <a href="README.it.md">Italiano</a> ·
  <a href="README.ja.md">日本語</a> ·
  <a href="README.ko.md">한국어</a> ·
  <b>Nederlands</b> ·
  <a href="README.pl.md">Polski</a> ·
  <a href="README.pt-BR.md">Português (BR)</a> ·
  <a href="README.ru.md">Русский</a> ·
  <a href="README.tr.md">Türkçe</a> ·
  <a href="README.zh-CN.md">简体中文</a>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  Voeg een complete helpdesk toe aan je Laravel-app.<br>
  Tickets, SLA's, escalatieregels, een inbox voor agents en een klantenportaal, draaiend op je eigen database.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Website</b></a> ·
  <a href="https://escalated.dev/docs"><b>Documentatie</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Wijzigingslog</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="De agent-inbox van Escalated: opgeslagen weergaven met aantallen, SLA-afteltimers en prioriteiten op elk ticket" width="100%">
</picture>

## Wat het doet

<table>
  <tr>
    <td width="40%">
      <h3>Een inbox waarin je agents de hele dag kunnen werken</h3>
      Gesprek, interne notities, standaardantwoorden en SLA-timers op één scherm. Tickets komen ook binnen per e-mail via Mailgun, Postmark, SES of IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Een geopend ticket in de werkruimte van de agent met de antwoordeditor">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="De rapportagepagina voor beheerders met reactietijden, CSAT en SLA-overschrijdingen">
      </picture>
    </td>
    <td width="40%">
      <h3>SLA's en escalatieregels die je kunt zien</h3>
      Reactie- en oplosdoelen per prioriteit met kantooruren, regels die escaleren of opnieuw toewijzen als een doel uitloopt, en rapporten over overschrijdingen.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Een klantenportaal dat al klaar is</h3>
      Klanten openen tickets, reageren en bekijken de status in je eigen app. Plaats de widget op andere sites met één scripttag.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="De ticketlijst in het klantenportaal">
      </picture>
    </td>
  </tr>
</table>

## Functies

<table>
<tr>
<td width="33%" valign="top">

**Tickets**
- Aanmaken, toewijzen, beantwoorden, oplossen, heropenen
- Configureerbare statusovergangen
- Een antwoord afsplitsen naar een nieuw ticket
- Snoozen met automatisch wekken
- Tags, bijlagen, activiteitentijdlijn

</td>
<td width="33%" valign="top">

**Werkruimte voor agents**
- Wachtrij met filters en opgeslagen weergaven
- Bulkacties en interne notities
- Standaardantwoorden
- Afdelingen met round-robin-toewijzing
- Realtime updates met terugval op polling

</td>
<td width="33%" valign="top">

**SLA's en escalatie**
- Reactie- en oplosdoelen per prioriteit
- Kantooruren
- Automatische detectie van overschrijdingen
- Regels die escaleren, opnieuw toewijzen of melden

</td>
</tr>
<tr>
<td width="33%" valign="top">

**Klanten**
- Selfserviceportaal: openen, reageren, volgen
- Geverifieerde gasttickets met privélinks
- Insluitbare widget, formulier of livechat
- Kennisbank, aan of uit

</td>
<td width="33%" valign="top">

**E-mail**
- Inkomend via Mailgun, Postmark, SES, IMAP
- Meldingen en webhooks per gebeurtenis
- Correcte threads in mailclients
- Templates in je eigen huisstijl

</td>
<td width="33%" valign="top">

**Beheer en toegang**
- Afdelingen, SLA-beleid, regels, rapporten
- Rollen, rechten, tweestapsverificatie, SSO
- Beheer binnen je eigen beheerpaneel
- Agent-API om tickets aan te maken

</td>
</tr>
</table>

<p align="center"><sub><a href="../features.md">Alle functies in detail</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

## Snel aan de slag

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Ga daarna naar `/support`. Vereist PHP 8.2+ en Laravel 11.39 of nieuwer. De [handleiding om te beginnen](../getting-started.md) behandelt het instellen van het gebruikersmodel en de toegangsgates.

## Meer informatie

- [Alle functies](../features.md) · [Aan de slag](../getting-started.md) · [Installatie](../installation.md)
- [Frontend-integratie en thema's](../frontend-integration.md) · [Aanpassen](../customization.md)
- [Configuratie, hostingmodi, events en routes](../operations.md) · [Configuratiereferentie](../configuration.md)
- [SLA-beleid](../sla-policies.md) · [Escalatieregels](../escalation-rules.md) · [Tickettoegang en routering](../ticket-access-and-routing.md)
- [Inkomende e-mail](../inbound-email.md) · [De widget insluiten](../embedding-the-widget.md) · [Gasttoegang](../guest-access.md)
- [Plugins](../plugin-sdk.md) · [Nieuwsbrieven](../newsletters.md) · [Multitenancy](../tenancy.md)

Escalated is ook beschikbaar voor [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) en [Filament](https://github.com/escalated-dev/escalated-filament). De UI staat in [escalated](https://github.com/escalated-dev/escalated).

## Licentie

MIT. Copyright (c) Escalated.dev. Zie [LICENSE](../../LICENSE).
