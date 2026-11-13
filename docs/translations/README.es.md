<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <b>Español</b> ·
  <a href="README.fr.md">Français</a> ·
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
  Añade un helpdesk completo a tu aplicación Laravel.<br>
  Tickets, SLA, reglas de escalado, una bandeja de entrada para agentes y un portal de clientes, funcionando en tu propia base de datos.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Sitio web</b></a> ·
  <a href="https://escalated.dev/docs"><b>Documentación</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Registro de cambios</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="La bandeja de entrada de agentes de Escalated: vistas guardadas con contadores, cuentas atrás de SLA y prioridades en cada ticket" width="100%">
</picture>

## Qué hace

<table>
  <tr>
    <td width="40%">
      <h3>Una bandeja de entrada en la que tus agentes pueden trabajar todo el día</h3>
      Conversación, notas internas, respuestas predefinidas y temporizadores de SLA en una sola pantalla. Los tickets también llegan por correo electrónico a través de Mailgun, Postmark, SES o IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Un ticket abierto en el espacio de trabajo del agente con el editor de respuestas">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="La página de informes de administración con tiempos de respuesta, CSAT e incumplimientos de SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>SLA y reglas de escalado que puedes ver</h3>
      Objetivos de respuesta y resolución por prioridad con horario laboral, reglas que escalan o reasignan cuando no se cumplen, e informes sobre incumplimientos.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Un portal de clientes que ya está hecho</h3>
      Los clientes abren tickets, responden y consultan el estado dentro de tu aplicación. Inserta el widget en otros sitios con una sola etiqueta script.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="La lista de tickets del portal de clientes">
      </picture>
    </td>
  </tr>
</table>

## Funciones

<table>
<tr>
<td width="33%" valign="top">

**Tickets**
- Crear, asignar, responder, resolver, reabrir
- Transiciones de estado configurables
- Dividir una respuesta en un ticket nuevo
- Posponer con reactivación automática
- Etiquetas, adjuntos, cronología de actividad

</td>
<td width="33%" valign="top">

**Espacio de trabajo del agente**
- Cola con filtros y vistas guardadas
- Acciones masivas y notas internas
- Respuestas predefinidas
- Departamentos con asignación por turnos
- Actualizaciones en tiempo real con sondeo como alternativa

</td>
<td width="33%" valign="top">

**SLA y escalado**
- Objetivos de respuesta y resolución por prioridad
- Horario laboral
- Detección automática de incumplimientos
- Reglas que escalan, reasignan o notifican

</td>
</tr>
<tr>
<td width="33%" valign="top">

**Clientes**
- Portal de autoservicio: abrir, responder, seguir
- Tickets de invitados verificados con enlaces privados
- Widget, formulario o chat en vivo integrables
- Base de conocimiento, activada o desactivada

</td>
<td width="33%" valign="top">

**Correo electrónico**
- Correo entrante vía Mailgun, Postmark, SES, IMAP
- Notificaciones y webhooks por evento
- Hilos correctos en los clientes de correo
- Plantillas con tu marca

</td>
<td width="33%" valign="top">

**Administración y acceso**
- Departamentos, políticas de SLA, reglas, informes
- Roles, permisos, doble factor, SSO
- Administración dentro de tu propio panel de administración
- API de agentes para crear tickets

</td>
</tr>
</table>

<p align="center"><sub><a href="../features.md">Todas las funciones en detalle</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

## Inicio rápido

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Después visita `/support`. Requiere PHP 8.2+ y Laravel 11.39 o posterior. La [guía de primeros pasos](../getting-started.md) explica cómo configurar el modelo de usuario y los gates de acceso.

## Más información

- [Todas las funciones](../features.md) · [Primeros pasos](../getting-started.md) · [Instalación](../installation.md)
- [Integración del frontend y temas](../frontend-integration.md) · [Personalización](../customization.md)
- [Configuración, modos de alojamiento, eventos y rutas](../operations.md) · [Referencia de configuración](../configuration.md)
- [Políticas de SLA](../sla-policies.md) · [Reglas de escalado](../escalation-rules.md) · [Acceso a tickets y enrutamiento](../ticket-access-and-routing.md)
- [Correo entrante](../inbound-email.md) · [Insertar el widget](../embedding-the-widget.md) · [Acceso de invitados](../guest-access.md)
- [Plugins](../plugin-sdk.md) · [Boletines](../newsletters.md) · [Multiinquilino](../tenancy.md)

Escalated también está disponible para [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) y [Filament](https://github.com/escalated-dev/escalated-filament). La interfaz está en [escalated](https://github.com/escalated-dev/escalated).

## Licencia

MIT. Copyright (c) Escalated.dev. Consulta [LICENSE](../../LICENSE).
