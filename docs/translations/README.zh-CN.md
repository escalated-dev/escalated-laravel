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
  <a href="README.tr.md">Türkçe</a> ·
  <b>简体中文</b>
</sub></p>

<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="72" height="72" alt="Escalated"></a>
</p>

<h1 align="center">Escalated for Laravel</h1>

<p align="center">
  为你的 Laravel 应用添加一套完整的帮助台。<br>
  工单、SLA、升级规则、客服收件箱和客户门户，全部运行在你自己的数据库上。
</p>

<p align="center">
  <a href="https://escalated.dev"><b>官网</b></a> ·
  <a href="https://escalated.dev/docs"><b>文档</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="../../CHANGELOG.md"><b>更新日志</b></a>
</p>

<br>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-dark.png">
  <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/inbox-light.png" alt="Escalated 客服收件箱：带计数的已保存视图，每个工单上都有 SLA 倒计时和优先级" width="100%">
</picture>

## 功能

<table>
  <tr>
    <td width="40%">
      <h3>客服可以整天使用的收件箱</h3>
      对话、内部备注、预设回复和 SLA 计时器都在同一个界面上。工单也可以通过 Mailgun、Postmark、SES 或 IMAP 以邮件形式进入。
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/ticket-light.png" alt="在客服工作区中打开的工单和回复编辑器">
      </picture>
    </td>
  </tr>
  <tr>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/dashboard-light.png" alt="管理后台的报表页面，显示响应时间、CSAT 和 SLA 违约情况">
      </picture>
    </td>
    <td width="40%">
      <h3>清晰可见的 SLA 和升级规则</h3>
      按优先级设定响应和解决目标并支持工作时间，目标延误时自动升级或重新分配，还提供违约报表。
    </td>
  </tr>
  <tr>
    <td width="40%">
      <h3>现成的客户门户</h3>
      客户可以在你的应用内提交工单、回复并查看状态。只需一个 script 标签，就能把小部件嵌入到其他网站。
    </td>
    <td width="60%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-dark.png">
        <img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/portal-light.png" alt="客户门户中的工单列表">
      </picture>
    </td>
  </tr>
</table>

## 功能列表

<table>
<tr>
<td width="33%" valign="top">

**工单**
- 创建、分配、回复、解决、重新打开
- 可配置的状态流转
- 将回复拆分为新工单
- 暂缓工单并自动唤醒
- 标签、附件、活动时间线

</td>
<td width="33%" valign="top">

**客服工作区**
- 带筛选和已保存视图的队列
- 批量操作和内部备注
- 预设回复
- 支持轮流分配的部门
- 实时更新，可回退到轮询

</td>
<td width="33%" valign="top">

**SLA 和升级**
- 按优先级设定响应和解决目标
- 工作时间
- 自动检测违约
- 可升级、重新分配或发送通知的规则

</td>
</tr>
<tr>
<td width="33%" valign="top">

**客户**
- 自助门户：提交、回复、跟踪
- 通过私密链接验证的访客工单
- 可嵌入的小部件、表单或在线聊天
- 知识库，可开可关

</td>
<td width="33%" valign="top">

**邮件**
- 通过 Mailgun、Postmark、SES、IMAP 接收邮件
- 按事件发送通知和 Webhook
- 在邮件客户端中正确归入同一会话
- 带品牌样式的模板

</td>
<td width="33%" valign="top">

**管理与权限**
- 部门、SLA 策略、规则、报表
- 角色、权限、双因素认证、SSO
- 在你自己的管理面板中使用管理后台
- 用于创建工单的客服 API

</td>
</tr>
</table>

<p align="center"><sub><a href="../features.md">所有功能详解</a></sub></p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel_11--13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11 to 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP_8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3"></a>
</p>

## 快速开始

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

然后访问 `/support`。需要 PHP 8.2+ 和 Laravel 11.39 或更高版本。[入门指南](../getting-started.md)介绍了用户模型的配置和访问门控（gate）。

## 了解更多

- [全部功能](../features.md) · [入门](../getting-started.md) · [安装](../installation.md)
- [前端集成与主题](../frontend-integration.md) · [自定义](../customization.md)
- [配置、托管模式、事件和路由](../operations.md) · [配置参考](../configuration.md)
- [SLA 策略](../sla-policies.md) · [升级规则](../escalation-rules.md) · [工单访问与路由](../ticket-access-and-routing.md)
- [入站邮件](../inbound-email.md) · [嵌入小部件](../embedding-the-widget.md) · [访客访问](../guest-access.md)
- [插件](../plugin-sdk.md) · [邮件通讯](../newsletters.md) · [多租户](../tenancy.md)

Escalated 也提供 [Rails](https://github.com/escalated-dev/escalated-rails)、[Django](https://github.com/escalated-dev/escalated-django)、[NestJS](https://github.com/escalated-dev/escalated-nestjs)、[AdonisJS](https://github.com/escalated-dev/escalated-adonis)、[Symfony](https://github.com/escalated-dev/escalated-symfony)、[Phoenix](https://github.com/escalated-dev/escalated-phoenix)、[Go](https://github.com/escalated-dev/escalated-go)、[.NET](https://github.com/escalated-dev/escalated-dotnet)、[Spring](https://github.com/escalated-dev/escalated-spring)、[WordPress](https://github.com/escalated-dev/escalated-wordpress) 和 [Filament](https://github.com/escalated-dev/escalated-filament) 版本。界面代码位于 [escalated](https://github.com/escalated-dev/escalated)。

## 许可证

MIT。Copyright (c) Escalated.dev。详见 [LICENSE](../../LICENSE)。
