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
  <a href="README.pt-BR.md">Português (BR)</a> ·
  <a href="README.ru.md">Русский</a> ·
  <b>Türkçe</b> ·
  <a href="README.zh-CN.md">简体中文</a>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  Laravel uygulamanıza eksiksiz bir yardım masası ekleyin.<br>
  Talepler, SLA'lar, eskalasyon kuralları, temsilci gelen kutusu ve müşteri portalı, hepsi kendi veritabanınızda çalışır.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>Web sitesi</b></a> ·
  <a href="https://escalated.dev/docs"><b>Dokümantasyon</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>Değişiklik günlüğü</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="Escalated temsilci gelen kutusu: sayılarıyla kayıtlı görünümler, her talepte SLA geri sayımları ve öncelikler" width="100%">
</picture>

## Neler yapar

<table>
  <tr>
    <td width="40%">
      <h3>Temsilcilerinizin gün boyu çalışabileceği bir gelen kutusu</h3>
      Yazışma, dahili notlar, hazır yanıtlar ve SLA sayaçları tek ekranda. Talepler Mailgun, Postmark, SES veya IMAP üzerinden e-postayla da gelir.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="Temsilci çalışma alanında yanıt düzenleyicisiyle açık bir talep">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="Yanıt sürelerini, CSAT'i ve SLA ihlallerini gösteren yönetici rapor sayfası">
      </picture>
    </td>
    <td width="40%">
      <h3>Görebildiğiniz SLA'lar ve eskalasyon kuralları</h3>
      Mesai saatlerini dikkate alan, önceliğe göre yanıt ve çözüm hedefleri, hedef kaçtığında eskalasyon yapan veya talebi yeniden atayan kurallar ve ihlal raporları.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>Hazır bir müşteri portalı</h3>
      Müşteriler uygulamanızın içinde talep açar, yanıt verir ve durumu takip eder. Widget'ı tek bir script etiketiyle başka sitelere yerleştirebilirsiniz.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="Müşteri portalındaki talep listesi">
      </picture>
    </td>
  </tr>
</table>

## Hızlı başlangıç

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

Ardından `/support` adresini ziyaret edin. PHP 8.2+ ve Laravel 11.39 veya üstü gerekir. [Başlangıç kılavuzu](../getting-started.md) kullanıcı modeli kurulumunu ve erişim gate'lerini anlatır.

## Daha fazla bilgi

- [Tüm özellikler](../features.md) · [Başlarken](../getting-started.md) · [Kurulum](../installation.md)
- [Frontend entegrasyonu ve temalar](../frontend-integration.md) · [Özelleştirme](../customization.md)
- [Yapılandırma, barındırma modları, olaylar ve rotalar](../operations.md) · [Yapılandırma başvurusu](../configuration.md)
- [SLA politikaları](../sla-policies.md) · [Eskalasyon kuralları](../escalation-rules.md) · [Talep erişimi ve yönlendirme](../ticket-access-and-routing.md)
- [Gelen e-posta](../inbound-email.md) · [Widget'ı yerleştirme](../embedding-the-widget.md) · [Misafir erişimi](../guest-access.md)
- [Eklentiler](../plugin-sdk.md) · [Bültenler](../newsletters.md) · [Çoklu kiracılık](../tenancy.md)

Escalated, [Rails](https://github.com/escalated-dev/escalated-rails), [Django](https://github.com/escalated-dev/escalated-django), [NestJS](https://github.com/escalated-dev/escalated-nestjs), [AdonisJS](https://github.com/escalated-dev/escalated-adonis), [Symfony](https://github.com/escalated-dev/escalated-symfony), [Phoenix](https://github.com/escalated-dev/escalated-phoenix), [Go](https://github.com/escalated-dev/escalated-go), [.NET](https://github.com/escalated-dev/escalated-dotnet), [Spring](https://github.com/escalated-dev/escalated-spring), [WordPress](https://github.com/escalated-dev/escalated-wordpress) ve [Filament](https://github.com/escalated-dev/escalated-filament) için de kullanılabilir. Arayüz [escalated](https://github.com/escalated-dev/escalated) deposunda bulunur.

## Lisans

MIT. Copyright (c) Escalated.dev. Ayrıntılar için [LICENSE](../../LICENSE) dosyasına bakın.
