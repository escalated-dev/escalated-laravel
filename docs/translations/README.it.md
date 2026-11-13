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

## Funzionalità

<table>
<tr>
<td width="33%" valign="top">

**Ticket**
- Creare, assegnare, rispondere, risolvere, riaprire
- Transizioni di stato configurabili
- Dividere una risposta in un nuovo ticket
- Posticipo con riattivazione automatica
- Tag, allegati, cronologia delle attività

</td>
<td width="33%" valign="top">

**Area di lavoro dell'agente**
- Coda con filtri e viste salvate
- Azioni di massa e note interne
- Risposte predefinite
- Reparti con assegnazione round-robin
- Aggiornamenti in tempo reale con polling come ripiego

</td>
<td width="33%" valign="top">

**SLA ed escalation**
- Obiettivi di risposta e risoluzione per priorità
- Orari lavorativi
- Rilevamento automatico delle violazioni
- Regole che eseguono l'escalation, riassegnano o notificano

</td>
</tr>
<tr>
<td width="33%" valign="top">

**Clienti**
- Portale self-service: aprire, rispondere, seguire
- Ticket ospite verificati con link privati
- Widget, modulo o live chat incorporabili
- Knowledge base, attivabile o disattivabile

</td>
<td width="33%" valign="top">

**Email**
- In entrata tramite Mailgun, Postmark, SES, IMAP
- Notifiche e webhook per evento
- Thread corretti nei client di posta
- Template con il tuo brand

</td>
<td width="33%" valign="top">

**Amministrazione e accesso**
- Reparti, policy SLA, regole, report
- Ruoli, permessi, autenticazione a due fattori, SSO
- Amministrazione nel tuo pannello di amministrazione
- API per agenti per creare ticket

</td>
</tr>
</table>

<p align="center"><sub><a href="../features.md">Tutte le funzionalità in dettaglio</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

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
