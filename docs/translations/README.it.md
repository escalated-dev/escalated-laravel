<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <a href="README.es.md">Español</a> ·
  <a href="README.fr.md">Français</a> ·
  <b>Italiano</b> ·
  <a href="README.ja.md">日本語</a> ·
  <a href="README.ko.md">한국어</a> ·
  <a href="README.nl.md">Nederlands</a> ·
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
  Aggiungi un helpdesk completo alla tua app Laravel.<br>
  Ticket, SLA, regole di escalation, una inbox per gli agenti e un portale clienti, sul tuo database.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Sito web</b></a> ·
  <a href="https://escalated.dev/docs"><b>Documentazione</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Registro delle modifiche</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="La inbox degli agenti di Escalated: viste salvate con conteggi, conti alla rovescia SLA e priorità su ogni ticket" width="100%">
</picture>

## Cosa fa

<table>
  <tr>
    <td width="40%">
      <h3>Una inbox in cui i tuoi agenti possono lavorare tutto il giorno</h3>
      Conversazione, note interne, risposte predefinite e timer SLA in un'unica schermata. I ticket arrivano anche via email tramite Mailgun, Postmark, SES o IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Un ticket aperto nell'area di lavoro dell'agente con l'editor di risposta">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="La pagina dei report di amministrazione con tempi di risposta, CSAT e violazioni degli SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>SLA e regole di escalation sempre visibili</h3>
      Obiettivi di risposta e risoluzione per priorità con orari lavorativi, regole che eseguono l'escalation o riassegnano quando un obiettivo slitta, e report sulle violazioni.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Un portale clienti già pronto</h3>
      I clienti aprono ticket, rispondono e controllano lo stato direttamente nella tua app. Incorpora il widget su altri siti con un solo tag script.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="L'elenco dei ticket nel portale clienti">
      </picture>
    </td>
  </tr>
</table>

## Avvio rapido

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Poi visita `/support`. Richiede PHP 8.2+ e Laravel 11.39 o successivo. La [guida introduttiva](../getting-started.md) spiega come configurare il modello utente e i gate di accesso.

## Per saperne di più

- [Tutte le funzionalità](../features.md) · [Per iniziare](../getting-started.md) · [Installazione](../installation.md)
- [Integrazione frontend e temi](../frontend-integration.md) · [Personalizzazione](../customization.md)
- [Configurazione, modalità di hosting, eventi e route](../operations.md) · [Riferimento della configurazione](../configuration.md)
- [Policy SLA](../sla-policies.md) · [Regole di escalation](../escalation-rules.md) · [Accesso ai ticket e instradamento](../ticket-access-and-routing.md)
- [Email in entrata](../inbound-email.md) · [Incorporare il widget](../embedding-the-widget.md) · [Accesso ospite](../guest-access.md)
- [Plugin](../plugin-sdk.md) · [Newsletter](../newsletters.md) · [Multi-tenancy](../tenancy.md)

Escalated è disponibile anche per [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) e [Filament](https://github.com/escalated-dev/escalated-filament). L'interfaccia si trova in [escalated](https://github.com/escalated-dev/escalated).

## Licenza

MIT. Copyright (c) Escalated.dev. Vedi [LICENSE](../../LICENSE).
