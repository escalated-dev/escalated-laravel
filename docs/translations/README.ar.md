<p align="center"><sub>
  <b>العربية</b> ·
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
  <a href="README.tr.md">Türkçe</a> ·
  <a href="README.zh-CN.md">简体中文</a>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  أضف مكتب دعم متكاملًا إلى تطبيق Laravel الخاص بك.<br>
  تذاكر، واتفاقيات مستوى الخدمة (SLA)، وقواعد تصعيد، وصندوق وارد للوكلاء، وبوابة للعملاء، تعمل كلها على قاعدة بياناتك الخاصة.
</p>

<p align="center">
  <a href="https://escalated.dev"><b>الموقع</b></a> ·
  <a href="https://escalated.dev/docs"><b>التوثيق</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>سجل التغييرات</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="صندوق الوارد للوكلاء في Escalated: عروض محفوظة مع أعدادها، وعدّاد تنازلي لـ SLA وأولوية على كل تذكرة" width="100%">
</picture>

## ما الذي يقدمه

<table>
  <tr>
    <td width="40%">
      <h3>صندوق وارد يمكن لوكلائك العمل فيه طوال اليوم</h3>
      المحادثة والملاحظات الداخلية والردود الجاهزة ومؤقتات SLA في شاشة واحدة. تصل التذاكر أيضًا عبر البريد الإلكتروني من خلال Mailgun أو Postmark أو SES أو IMAP.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="تذكرة مفتوحة في مساحة عمل الوكيل مع محرر الرد">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="صفحة التقارير في لوحة الإدارة مع أوقات الاستجابة وCSAT وحالات خرق SLA">
      </picture>
    </td>
    <td width="40%">
      <h3>اتفاقيات SLA وقواعد تصعيد واضحة أمامك</h3>
      أهداف للاستجابة والحل حسب الأولوية مع مراعاة ساعات العمل، وقواعد تصعّد التذكرة أو تعيد إسنادها عند التأخر، وتقارير عن حالات الخرق.
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>بوابة عملاء جاهزة</h3>
      يفتح العملاء التذاكر ويردون عليها ويتابعون حالتها داخل تطبيقك. يمكنك تضمين الأداة في مواقع أخرى بوسم script واحد.
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="قائمة التذاكر في بوابة العملاء">
      </picture>
    </td>
  </tr>
</table>

## البدء السريع

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

ثم افتح `/support`. يتطلب PHP 8.2+ وLaravel 11.39 أو أحدث. يشرح [دليل البدء](../getting-started.md) إعداد نموذج المستخدم وبوابات الوصول (gates).

## اعرف المزيد

- [كل الميزات](../features.md) · [البدء](../getting-started.md) · [التثبيت](../installation.md)
- [تكامل الواجهة الأمامية والسمات](../frontend-integration.md) · [التخصيص](../customization.md)
- [الإعدادات وأنماط الاستضافة والأحداث والمسارات](../operations.md) · [مرجع الإعدادات](../configuration.md)
- [سياسات SLA](../sla-policies.md) · [قواعد التصعيد](../escalation-rules.md) · [الوصول إلى التذاكر وتوجيهها](../ticket-access-and-routing.md)
- [البريد الوارد](../inbound-email.md) · [تضمين الأداة](../embedding-the-widget.md) · [وصول الضيوف](../guest-access.md)
- [الإضافات](../plugin-sdk.md) · [النشرات البريدية](../newsletters.md) · [تعدد المستأجرين](../tenancy.md)

يتوفر Escalated أيضًا لـ [Rails](https://github.com/escalated-dev/escalated-rails)، [Django](https://github.com/escalated-dev/escalated-django)، [NestJS](https://github.com/escalated-dev/escalated-nestjs)، [AdonisJS](https://github.com/escalated-dev/escalated-adonis)، [Symfony](https://github.com/escalated-dev/escalated-symfony)، [Phoenix](https://github.com/escalated-dev/escalated-phoenix)، [Go](https://github.com/escalated-dev/escalated-go)، [.NET](https://github.com/escalated-dev/escalated-dotnet)، [Spring](https://github.com/escalated-dev/escalated-spring)، [WordPress](https://github.com/escalated-dev/escalated-wordpress) و[Filament](https://github.com/escalated-dev/escalated-filament). توجد الواجهة في [escalated](https://github.com/escalated-dev/escalated).

## الترخيص

MIT. Copyright (c) Escalated.dev. راجع [LICENSE](../../LICENSE).
