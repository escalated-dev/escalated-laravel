<p align="center"><sub>
  <a href="README.ar.md">العربية</a> ·
  <a href="README.de.md">Deutsch</a> ·
  <a href="../../README.md">English</a> ·
  <a href="README.es.md">Español</a> ·
  <a href="README.fr.md">Français</a> ·
  <a href="README.it.md">Italiano</a> ·
  <b>日本語</b> ·
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
  Laravel アプリに本格的なヘルプデスクを追加できます。<br>
  チケット、SLA、エスカレーションルール、エージェント用の受信トレイ、カスタマーポータルが、あなた自身のデータベース上で動きます。
</p>

<p align="center">
  <a href="https://escalated.dev"><b>ウェブサイト</b></a> ·
  <a href="https://escalated.dev/docs"><b>ドキュメント</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>変更履歴</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="Escalated のエージェント受信トレイ: 件数付きの保存済みビュー、各チケットの SLA カウントダウンと優先度" width="100%">
</picture>

## できること

<table>
  <tr>
    <td width="40%">
      <h3>エージェントが一日中作業できる受信トレイ</h3>
      会話、内部メモ、定型返信、SLA タイマーを 1 つの画面にまとめています。チケットは Mailgun、Postmark、SES、IMAP 経由のメールでも届きます。
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="エージェントのワークスペースで開いたチケットと返信エディター">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="応答時間、CSAT、SLA 違反を表示する管理者向けレポートページ">
      </picture>
    </td>
    <td width="40%">
      <h3>ひと目でわかる SLA とエスカレーションルール</h3>
      営業時間を考慮した優先度ごとの応答・解決目標、目標に遅れたときにエスカレーションや再割り当てを行うルール、違反に関するレポートを備えています。
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>すぐに使えるカスタマーポータル</h3>
      顧客はあなたのアプリ内でチケットを作成し、返信し、ステータスを確認できます。script タグ 1 つで、ウィジェットを他のサイトに埋め込めます。
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="カスタマーポータルのチケット一覧">
      </picture>
    </td>
  </tr>
</table>

## クイックスタート

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

その後 `/support` にアクセスしてください。PHP 8.2 以上と Laravel 11.39 以降が必要です。ユーザーモデルの設定とアクセスゲートについては[スタートガイド](../getting-started.md)を参照してください。

## 詳しく知る

- [すべての機能](../features.md) · [はじめに](../getting-started.md) · [インストール](../installation.md)
- [フロントエンド統合とテーマ](../frontend-integration.md) · [カスタマイズ](../customization.md)
- [設定、ホスティングモード、イベント、ルート](../operations.md) · [設定リファレンス](../configuration.md)
- [SLA ポリシー](../sla-policies.md) · [エスカレーションルール](../escalation-rules.md) · [チケットのアクセスとルーティング](../ticket-access-and-routing.md)
- [受信メール](../inbound-email.md) · [ウィジェットの埋め込み](../embedding-the-widget.md) · [ゲストアクセス](../guest-access.md)
- [プラグイン](../plugin-sdk.md) · [ニュースレター](../newsletters.md) · [マルチテナンシー](../tenancy.md)

Escalated は [Rails](https://github.com/escalated-dev/escalated-rails)、[Django](https://github.com/escalated-dev/escalated-django)、[NestJS](https://github.com/escalated-dev/escalated-nestjs)、[AdonisJS](https://github.com/escalated-dev/escalated-adonis)、[Symfony](https://github.com/escalated-dev/escalated-symfony)、[Phoenix](https://github.com/escalated-dev/escalated-phoenix)、[Go](https://github.com/escalated-dev/escalated-go)、[.NET](https://github.com/escalated-dev/escalated-dotnet)、[Spring](https://github.com/escalated-dev/escalated-spring)、[WordPress](https://github.com/escalated-dev/escalated-wordpress)、[Filament](https://github.com/escalated-dev/escalated-filament) 向けにも提供しています。UI は [escalated](https://github.com/escalated-dev/escalated) にあります。

## ライセンス

MIT。Copyright (c) Escalated.dev。詳細は [LICENSE](../../LICENSE) を参照してください。
