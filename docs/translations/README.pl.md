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
  <b>Polski</b> ·
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
  Dodaj kompletny helpdesk do swojej aplikacji Laravel.<br>
  Zgłoszenia, SLA, reguły eskalacji, skrzynka dla agentów i portal klienta, działające na Twojej własnej bazie danych.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Strona</b></a> ·
  <a href="https://escalated.dev/docs"><b>Dokumentacja</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Lista zmian</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="Skrzynka agenta w Escalated: zapisane widoki z licznikami, odliczanie SLA i priorytety przy każdym zgłoszeniu" width="100%">
</picture>

## Co robi

<table>
  <tr>
    <td width="40%">
      <h3>Skrzynka, w której Twoi agenci mogą pracować cały dzień</h3>
      Rozmowa, notatki wewnętrzne, gotowe odpowiedzi i liczniki SLA na jednym ekranie. Zgłoszenia przychodzą też e-mailem przez Mailgun, Postmark, SES lub IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Zgłoszenie otwarte w obszarze roboczym agenta z edytorem odpowiedzi">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="Strona raportów w panelu administracyjnym z czasami odpowiedzi, CSAT i naruszeniami SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>SLA i reguły eskalacji, które widać</h3>
      Cele czasu odpowiedzi i rozwiązania dla każdego priorytetu z uwzględnieniem godzin pracy, reguły, które eskalują lub ponownie przypisują zgłoszenie, gdy termin się przesuwa, oraz raporty naruszeń.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Gotowy portal klienta</h3>
      Klienci otwierają zgłoszenia, odpowiadają i sprawdzają status w Twojej aplikacji. Widżet osadzisz na innych stronach jednym tagiem script.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="Lista zgłoszeń w portalu klienta">
      </picture>
    </td>
  </tr>
</table>

## Funkcje

<table>
<tr>
<td width="33%" valign="top">

**Zgłoszenia**
- Tworzenie, przypisywanie, odpowiadanie, rozwiązywanie, ponowne otwieranie
- Konfigurowalne przejścia statusów
- Wydzielanie odpowiedzi do nowego zgłoszenia
- Odkładanie z automatycznym wybudzaniem
- Tagi, załączniki, oś czasu aktywności

</td>
<td width="33%" valign="top">

**Obszar roboczy agenta**
- Kolejka z filtrami i zapisanymi widokami
- Akcje zbiorcze i notatki wewnętrzne
- Gotowe odpowiedzi
- Działy z przydziałem round-robin
- Aktualizacje w czasie rzeczywistym z pollingiem jako rezerwą

</td>
<td width="33%" valign="top">

**SLA i eskalacja**
- Cele odpowiedzi i rozwiązania dla każdego priorytetu
- Godziny pracy
- Automatyczne wykrywanie naruszeń
- Reguły, które eskalują, przypisują ponownie lub powiadamiają

</td>
</tr>
<tr>
<td width="33%" valign="top">

**Klienci**
- Portal samoobsługowy: otwieranie, odpowiadanie, śledzenie
- Zweryfikowane zgłoszenia gości z prywatnymi linkami
- Osadzany widżet, formularz lub czat na żywo
- Baza wiedzy, włączana lub wyłączana

</td>
<td width="33%" valign="top">

**E-mail**
- Poczta przychodząca przez Mailgun, Postmark, SES, IMAP
- Powiadomienia i webhooki dla każdego zdarzenia
- Poprawne wątki w klientach poczty
- Szablony z Twoim brandingiem

</td>
<td width="33%" valign="top">

**Administracja i dostęp**
- Działy, polityki SLA, reguły, raporty
- Role, uprawnienia, uwierzytelnianie dwuskładnikowe, SSO
- Panel administracyjny w Twoim własnym panelu admina
- API dla agentów do tworzenia zgłoszeń

</td>
</tr>
</table>

<p align="center"><sub><a href="../features.md">Wszystkie funkcje w szczegółach</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

## Szybki start

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Następnie otwórz `/support`. Wymaga PHP 8.2+ i Laravel 11.39 lub nowszego. [Przewodnik na start](../getting-started.md) opisuje konfigurację modelu użytkownika i bramek dostępu (gates).

## Dowiedz się więcej

- [Wszystkie funkcje](../features.md) · [Pierwsze kroki](../getting-started.md) · [Instalacja](../installation.md)
- [Integracja frontendu i motywy](../frontend-integration.md) · [Dostosowywanie](../customization.md)
- [Konfiguracja, tryby hostingu, zdarzenia i trasy](../operations.md) · [Opis konfiguracji](../configuration.md)
- [Polityki SLA](../sla-policies.md) · [Reguły eskalacji](../escalation-rules.md) · [Dostęp do zgłoszeń i routing](../ticket-access-and-routing.md)
- [Poczta przychodząca](../inbound-email.md) · [Osadzanie widżetu](../embedding-the-widget.md) · [Dostęp dla gości](../guest-access.md)
- [Wtyczki](../plugin-sdk.md) · [Newslettery](../newsletters.md) · [Wielodostępność](../tenancy.md)

Escalated jest dostępny również dla [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) i [Filament](https://github.com/escalated-dev/escalated-filament). Interfejs znajduje się w [escalated](https://github.com/escalated-dev/escalated).

## Licencja

MIT. Copyright (c) Escalated.dev. Zobacz [LICENSE](../../LICENSE).
