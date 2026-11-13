<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <a href="README.es.md">Español</a> ·
  <b>Français</b> ·
  <a href="README.it.md">Italiano</a> ·
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
  Ajoutez un helpdesk complet à votre application Laravel.<br>
  Tickets, SLA, règles d'escalade, une boîte de réception pour les agents et un portail client, sur votre propre base de données.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Site web</b></a> ·
  <a href="https://escalated.dev/docs"><b>Documentation</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Journal des modifications</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="La boîte de réception des agents d'Escalated : vues enregistrées avec compteurs, comptes à rebours SLA et priorités sur chaque ticket" width="100%">
</picture>

## Ce qu'il fait

<table>
  <tr>
    <td width="40%">
      <h3>Une boîte de réception où vos agents peuvent travailler toute la journée</h3>
      Conversation, notes internes, réponses prédéfinies et minuteurs SLA sur un seul écran. Les tickets arrivent aussi par e-mail via Mailgun, Postmark, SES ou IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Un ticket ouvert dans l'espace de travail de l'agent avec l'éditeur de réponse">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="La page de rapports de l'administration avec les temps de réponse, le CSAT et les dépassements de SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>Des SLA et des règles d'escalade bien visibles</h3>
      Des objectifs de réponse et de résolution par priorité avec heures ouvrées, des règles qui escaladent ou réattribuent quand un objectif glisse, et des rapports sur les dépassements.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Un portail client déjà prêt</h3>
      Vos clients ouvrent des tickets, répondent et suivent leur statut dans votre application. Intégrez le widget sur d'autres sites avec une seule balise script.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="La liste des tickets du portail client">
      </picture>
    </td>
  </tr>
</table>

## Fonctionnalités

<table>
<tr>
<td width="33%" valign="top">

**Tickets**
- Créer, assigner, répondre, résoudre, rouvrir
- Transitions de statut configurables
- Scinder une réponse en nouveau ticket
- Mise en veille avec réveil automatique
- Étiquettes, pièces jointes, historique d'activité

</td>
<td width="33%" valign="top">

**Espace de travail de l'agent**
- File d'attente avec filtres et vues enregistrées
- Actions groupées et notes internes
- Réponses prédéfinies
- Départements avec attribution à tour de rôle
- Mises à jour en temps réel avec repli sur le polling

</td>
<td width="33%" valign="top">

**SLA et escalade**
- Objectifs de réponse et de résolution par priorité
- Heures ouvrées
- Détection automatique des dépassements
- Règles qui escaladent, réattribuent ou notifient

</td>
</tr>
<tr>
<td width="33%" valign="top">

**Clients**
- Portail en libre-service : ouvrir, répondre, suivre
- Tickets invités vérifiés avec liens privés
- Widget, formulaire ou chat en direct intégrables
- Base de connaissances, activable ou non

</td>
<td width="33%" valign="top">

**E-mail**
- Réception via Mailgun, Postmark, SES, IMAP
- Notifications et webhooks par événement
- Fils de discussion corrects dans les clients mail
- Modèles à votre image

</td>
<td width="33%" valign="top">

**Administration et accès**
- Départements, politiques SLA, règles, rapports
- Rôles, permissions, double authentification, SSO
- Administration dans votre propre panneau d'administration
- API agent pour créer des tickets

</td>
</tr>
</table>

<p align="center"><sub><a href="../features.md">Toutes les fonctionnalités en détail</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

## Démarrage rapide

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Ouvrez ensuite `/support`. Nécessite PHP 8.2+ et Laravel 11.39 ou plus récent. Le [guide de démarrage](../getting-started.md) explique la configuration du modèle utilisateur et des gates d'accès.

## En savoir plus

- [Toutes les fonctionnalités](../features.md) · [Premiers pas](../getting-started.md) · [Installation](../installation.md)
- [Intégration frontend et thèmes](../frontend-integration.md) · [Personnalisation](../customization.md)
- [Configuration, modes d'hébergement, événements et routes](../operations.md) · [Référence de configuration](../configuration.md)
- [Politiques SLA](../sla-policies.md) · [Règles d'escalade](../escalation-rules.md) · [Accès aux tickets et routage](../ticket-access-and-routing.md)
- [E-mails entrants](../inbound-email.md) · [Intégrer le widget](../embedding-the-widget.md) · [Accès invité](../guest-access.md)
- [Plugins](../plugin-sdk.md) · [Newsletters](../newsletters.md) · [Multi-tenant](../tenancy.md)

Escalated est aussi disponible pour [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) et [Filament](https://github.com/escalated-dev/escalated-filament). L'interface se trouve dans [escalated](https://github.com/escalated-dev/escalated).

## Licence

MIT. Copyright (c) Escalated.dev. Voir [LICENSE](../../LICENSE).
