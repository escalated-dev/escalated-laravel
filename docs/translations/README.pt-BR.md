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
  <b>Português (BR)</b> ·
  <a href="README.ru.md">Русский</a> ·
  <a href="README.tr.md">Türkçe</a> ·
  <a href="README.zh-CN.md">简体中文</a>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  Adicione um helpdesk completo ao seu app Laravel.<br>
  Tickets, SLAs, regras de escalonamento, uma caixa de entrada para agentes e um portal do cliente, rodando no seu próprio banco de dados.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Site</b></a> ·
  <a href="https://escalated.dev/docs"><b>Documentação</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Histórico de mudanças</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="A caixa de entrada de agentes do Escalated: visualizações salvas com contagens, contagem regressiva de SLA e prioridades em cada ticket" width="100%">
</picture>

## O que ele faz

<table>
  <tr>
    <td width="40%">
      <h3>Uma caixa de entrada em que seus agentes conseguem trabalhar o dia todo</h3>
      Conversa, notas internas, respostas prontas e timers de SLA em uma única tela. Os tickets também chegam por e-mail via Mailgun, Postmark, SES ou IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Um ticket aberto no espaço de trabalho do agente com o editor de resposta">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="A página de relatórios do admin com tempos de resposta, CSAT e violações de SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>SLAs e regras de escalonamento que você consegue ver</h3>
      Metas de resposta e resolução por prioridade com horário comercial, regras que escalonam ou reatribuem quando uma meta atrasa e relatórios de violações.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Um portal do cliente já pronto</h3>
      Os clientes abrem tickets, respondem e acompanham o status dentro do seu app. Incorpore o widget em outros sites com uma única tag script.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="A lista de tickets do portal do cliente">
      </picture>
    </td>
  </tr>
</table>

## Início rápido

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Depois acesse `/support`. Requer PHP 8.2+ e Laravel 11.39 ou mais recente. O [guia de primeiros passos](../getting-started.md) explica a configuração do model de usuário e dos gates de acesso.

## Saiba mais

- [Todos os recursos](../features.md) · [Primeiros passos](../getting-started.md) · [Instalação](../installation.md)
- [Integração com o frontend e temas](../frontend-integration.md) · [Personalização](../customization.md)
- [Configuração, modos de hospedagem, eventos e rotas](../operations.md) · [Referência de configuração](../configuration.md)
- [Políticas de SLA](../sla-policies.md) · [Regras de escalonamento](../escalation-rules.md) · [Acesso a tickets e roteamento](../ticket-access-and-routing.md)
- [E-mail de entrada](../inbound-email.md) · [Incorporando o widget](../embedding-the-widget.md) · [Acesso de convidados](../guest-access.md)
- [Plugins](../plugin-sdk.md) · [Newsletters](../newsletters.md) · [Multitenancy](../tenancy.md)

O Escalated também está disponível para [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) e [Filament](https://github.com/escalated-dev/escalated-filament). A interface fica em [escalated](https://github.com/escalated-dev/escalated).

## Licença

MIT. Copyright (c) Escalated.dev. Veja [LICENSE](../../LICENSE).
