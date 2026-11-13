<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <a href="README.es.md">Español</a> ·
  <a href="README.fr.md">Français</a> ·
  <a href="README.it.md">Italiano</a> ·
  <a href="README.ja.md">日本語</a> ·
  <b>한국어</b> ·
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
  Laravel 앱에 완전한 헬프데스크를 추가하세요.<br>
  티켓, SLA, 에스컬레이션 규칙, 상담원 받은편지함, 고객 포털이 여러분의 데이터베이스에서 동작합니다.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>웹사이트</b></a> ·
  <a href="https://escalated.dev/docs"><b>문서</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>변경 내역</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="Escalated 상담원 받은편지함: 개수가 표시된 저장된 보기, 모든 티켓의 SLA 카운트다운과 우선순위" width="100%">
</picture>

## 주요 기능

<table>
  <tr>
    <td width="40%">
      <h3>상담원이 하루 종일 일할 수 있는 받은편지함</h3>
      대화, 내부 메모, 상용구 답변, SLA 타이머를 한 화면에서 볼 수 있습니다. 티켓은 Mailgun, Postmark, SES 또는 IMAP을 통해 이메일로도 들어옵니다.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="답장 작성기가 표시된 상담원 작업 공간에서 열린 티켓">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="응답 시간, CSAT, SLA 위반을 보여주는 관리자 보고서 페이지">
      </picture>
    </td>
    <td width="40%">
      <h3>한눈에 보이는 SLA와 에스컬레이션 규칙</h3>
      영업시간을 반영한 우선순위별 응답 및 해결 목표, 목표가 지연되면 에스컬레이션하거나 재할당하는 규칙, 위반 보고서를 제공합니다.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>이미 만들어진 고객 포털</h3>
      고객은 여러분의 앱 안에서 티켓을 열고, 답장하고, 상태를 확인합니다. script 태그 하나로 다른 사이트에 위젯을 삽입할 수 있습니다.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="고객 포털의 티켓 목록">
      </picture>
    </td>
  </tr>
</table>

## 빠른 시작

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

그런 다음 `/support`에 접속하세요. PHP 8.2 이상과 Laravel 11.39 이상이 필요합니다. 사용자 모델 설정과 접근 게이트는 [시작 가이드](../getting-started.md)에서 다룹니다.

## 더 알아보기

- [전체 기능](../features.md) · [시작하기](../getting-started.md) · [설치](../installation.md)
- [프론트엔드 통합과 테마](../frontend-integration.md) · [커스터마이징](../customization.md)
- [설정, 호스팅 모드, 이벤트, 라우트](../operations.md) · [설정 레퍼런스](../configuration.md)
- [SLA 정책](../sla-policies.md) · [에스컬레이션 규칙](../escalation-rules.md) · [티켓 접근과 라우팅](../ticket-access-and-routing.md)
- [수신 이메일](../inbound-email.md) · [위젯 삽입](../embedding-the-widget.md) · [게스트 접근](../guest-access.md)
- [플러그인](../plugin-sdk.md) · [뉴스레터](../newsletters.md) · [멀티테넌시](../tenancy.md)

Escalated는 [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress), [Filament](https://github.com/escalated-dev/escalated-filament)용으로도 제공됩니다. UI는 [escalated](https://github.com/escalated-dev/escalated) 저장소에 있습니다.

## 라이선스

MIT. Copyright (c) Escalated.dev. [LICENSE](../../LICENSE)를 참고하세요.
