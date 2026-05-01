<p align="center">
  <a href="README.ar.md">العربية</a> •
  <a href="README.de.md">Deutsch</a> •
  <a href="../../README.md">English</a> •
  <a href="README.es.md">Español</a> •
  <a href="README.fr.md">Français</a> •
  <a href="README.it.md">Italiano</a> •
  <a href="README.ja.md">日本語</a> •
  <a href="README.ko.md">한국어</a> •
  <a href="README.nl.md">Nederlands</a> •
  <a href="README.pl.md">Polski</a> •
  <a href="README.pt-BR.md">Português (BR)</a> •
  <a href="README.ru.md">Русский</a> •
  <a href="README.tr.md">Türkçe</a> •
  <b>简体中文</b>
</p>

# Escalated for Laravel

[![Tests](https://github.com/escalated-dev/escalated-laravel/actions/workflows/laravel.yml/badge.svg)](https://github.com/escalated-dev/escalated-laravel/actions/workflows/laravel.yml)
[![FOSSA Status](https://app.fossa.com/api/projects/custom%2B62107%2Fgithub.com%2Fescalated-dev%2Fescalated-laravel.svg?type=shield)](https://app.fossa.com/projects/custom%2B62107%2Fgithub.com%2Fescalated-dev%2Fescalated-laravel?ref=badge_shield)
[![Laravel](https://img.shields.io/badge/laravel-11.x--13.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/php-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

一个功能完整、可嵌入的 Laravel 支持工单系统。将其添加到任何应用中 — 即可获得完整的帮助台，包含 SLA 跟踪、升级规则、客服工作流和客户门户。无需外部服务。

> **[escalated.dev](https://escalated.dev)** — 了解更多、查看演示，并比较云端与自托管选项。

**三种托管模式。** 完全自托管运行，同步到中央云以获得多应用可见性，或将所有内容代理到云端。只需更改一个配置即可切换模式。

## 功能特性

- **工单生命周期** — 创建、分配、回复、解决、关闭、重新打开，支持可配置的状态转换
- **SLA 引擎** — 按优先级的响应和解决目标、工作时间计算、自动违规检测
- **升级规则** — 基于条件的规则，自动升级、重新排列优先级、重新分配或通知
- **客服面板** — 带过滤器、批量操作、内部备注、预设回复的工单队列
- **客户门户** — 自助工单创建、回复和状态跟踪
- **管理面板** — 管理部门、SLA 策略、升级规则、标签和查看报告
- **文件附件** — 拖拽上传，可配置存储和大小限制
- **活动时间线** — 每个工单上每个操作的完整审计日志
- **邮件通知** — 可按事件配置的通知，支持 webhook
- **部门路由** — 将客服组织到部门，支持自动分配（轮询）
- **标签系统** — 使用彩色标签分类工单
- **访客工单** — 通过访客令牌的魔法链接访问进行匿名工单提交
- **入站邮件** — 通过邮件创建和回复工单 (Mailgun, Postmark, AWS SES, IMAP)
- **Inertia.js + Vue 3 UI** — 通过 [`@escalated-dev/escalated`](https://github.com/escalated-dev/escalated) 共享前端
- **工单拆分** — 将回复拆分为新的独立工单，同时保留原始上下文
- **Ticket snooze** — 使用预设延迟工单（1小时、4小时、明天、下周）；Artisan 命令 `escalated:wake-snoozed-tickets` 按计划自动唤醒
- **保存的视图 / 自定义队列** — 将过滤器预设保存、命名并共享为可重用的工单视图
- **Embeddable support widget** — 通过 `/support/widget/*` 路由提供的轻量级 `<script>` 小部件，包含知识库搜索、工单表单和状态检查
- **邮件线程** — 发送的邮件包含正确的 `In-Reply-To` 和 `References` 头部，以在邮件客户端中实现正确的线程化
- **品牌邮件模板** — 所有发送邮件的可配置 logo、主色和页脚文本
- **Real-time broadcasting** — 通过 Pusher、Reverb 或 Soketi 进行可选广播，带有自动轮询回退
- **知识库开关** — 从管理设置中启用或禁用公共知识库
- **CI: Laravel Pint** — 在每个拉取请求中自动执行代码风格

## 环境要求

- PHP 8.2+
- Laravel 11.x, 12.x, or 13.x
- Node.js 18+ (用于前端资源)

## 快速开始

```bash
composer require escalated-dev/escalated-laravel
npm install @escalated-dev/escalated
php artisan escalated:install
php artisan migrate
```

The install command will offer to automatically configure your User model with the `Ticketable` interface and `HasTickets` trait. If you prefer to do this manually, or if you use a custom user model, add the following:

```php
use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;

class User extends Authenticatable implements Ticketable
{
    use HasTickets;
}
```

Define authorization gates in a service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('escalated-admin', fn ($user) => $user->is_admin);
Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
```

Visit `/support` — you're live.

## 前端集成

Escalated ships a Vue component library and default pages via the [`@escalated-dev/escalated`](https://github.com/escalated-dev/escalated) npm package.

### 1. Tailwind 内容

将 Escalated 包添加到 Tailwind 的 `content` 配置中，以确保其类不会被清除：

```js
// tailwind.config.js
content: [
    // ... your existing paths
    './node_modules/@escalated-dev/escalated/src/**/*.vue',
],
```

### 2. 页面解析器

Add the Escalated page resolver to your `app.ts`:

```ts
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const escalatedPages = import.meta.glob(
    '../../node_modules/@escalated-dev/escalated/src/pages/**/*.vue',
);

createInertiaApp({
    resolve: (name) => {
        if (name.startsWith('Escalated/')) {
            const path = name.replace('Escalated/', '');
            return resolvePageComponent(
                `../../node_modules/@escalated-dev/escalated/src/pages/${path}.vue`,
                escalatedPages,
            );
        }
        return resolvePageComponent(`./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'));
    },
    // ...
});
```

### 3. 主题（可选）

注册 `EscalatedPlugin` 以在您的应用布局内渲染 Escalated 页面 — 无需页面复制：

```ts
import { EscalatedPlugin } from '@escalated-dev/escalated';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

createInertiaApp({
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(EscalatedPlugin, {
                layout: AuthenticatedLayout,
            })
            .mount(el);
    },
});
```

Your layout component must accept a `#header` slot and a default slot. Escalated will render its sub-navigation in the header and page content in the default slot.

Without the plugin, Escalated uses its own standalone layout with a simple nav bar.

### CSS 自定义属性

Pass a `theme` option to customize colors and radii:

```ts
app.use(EscalatedPlugin, {
    layout: AuthenticatedLayout,
    theme: {
        primary: '#3b82f6',
        radius: '0.75rem',
    }
})
```

| Property | Default | Description |
|----------|---------|-------------|
| `--esc-primary` | `#4f46e5` | Primary action color |
| `--esc-primary-hover` | auto-darkened | Primary hover color |
| `--esc-radius` | `0.5rem` | Border radius for inputs and buttons |
| `--esc-radius-lg` | auto-scaled | Border radius for cards and panels |
| `--esc-font-family` | inherit | Font family override |

### 可用组件

| Component | Description |
|-----------|-------------|
| `ActivityTimeline` | Full audit log of ticket events |
| `AssigneeSelect` | Agent assignment dropdown |
| `AttachmentList` | File attachment display |
| `FileDropzone` | Drag-and-drop file upload |
| `PriorityBadge` | Priority level indicator |
| `ReplyComposer` | Rich text reply editor |
| `ReplyThread` | Chronological message thread |
| `SlaTimer` | SLA countdown display |
| `StatsCard` | Metric card for dashboards |
| `StatusBadge` | Ticket status indicator |
| `TagSelect` | Tag picker with colors |
| `TicketFilters` | Search and filter controls |
| `TicketList` | Paginated ticket table |
| `TicketSidebar` | Ticket metadata sidebar |

### Inertia 共享 Props

Escalated automatically shares data to all Inertia pages via `page.props.escalated`:

```js
page.props.escalated = {
    prefix: 'support',     // Route prefix from config
    is_agent: true,        // Current user can access agent views
    is_admin: false,       // Current user can access admin views
}
```

Use these to conditionally show nav links or restrict UI elements.

## 托管模式

### Self-Hosted（默认）

所有数据保留在您的数据库中。无外部调用。完全自主。

```php
// config/escalated.php
'mode' => 'self-hosted',
```

### 同步模式

本地数据库 + 自动同步到 `cloud.escalated.dev` 以实现跨多个应用的统一收件箱。如果云端不可达，您的应用继续工作 — 事件会排队并重试。

```php
'mode' => 'synced',
'hosted' => [
    'api_url' => 'https://cloud.escalated.dev/api/v1',
    'api_key' => env('ESCALATED_API_KEY'),
],
```

### 云端

所有工单数据代理到云 API。您的应用处理认证和渲染 UI，但存储在云端。每个 API 密钥支持多个域名。

```php
'mode' => 'cloud',
```

三种模式共享相同的控制器、UI 和业务逻辑。驱动模式处理其余部分。

## 发布资源

```bash
# Email templates
php artisan vendor:publish --tag=escalated-views

# Config file
php artisan vendor:publish --tag=escalated-config

# Database migrations
php artisan vendor:publish --tag=escalated-migrations
```

## 调度

将这些添加到您的调度器以实现 SLA 和升级自动化：

```php
// app/Console/Kernel.php or routes/console.php
Schedule::command('escalated:check-sla')->everyMinute();
Schedule::command('escalated:evaluate-escalations')->everyFiveMinutes();
Schedule::command('escalated:close-resolved')->daily();
Schedule::command('escalated:purge-activities')->weekly();
Schedule::command('escalated:poll-imap')->everyMinute(); // Only if using IMAP adapter
```

## 配置

All config lives in `config/escalated.php`. Key options:

```php
'mode' => 'self-hosted',              // self-hosted | synced | cloud
'user_model' => App\Models\User::class,
'table_prefix' => 'escalated_',
'default_priority' => 'medium',

'routes' => [
    'prefix' => 'support',
    'middleware' => ['web', 'auth'],
],

'tickets' => [
    'allow_customer_close' => true,
    'auto_close_resolved_after_days' => 7,
],

'sla' => [
    'enabled' => true,
    'business_hours_only' => false,
    'business_hours' => [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'UTC',
        'days' => [1, 2, 3, 4, 5],
    ],
],
```

See the [full configuration reference](docs/configuration.md).

## 事件

Every ticket action dispatches an event you can listen to:

| Event | When |
|-------|------|
| `TicketCreated` | New ticket |
| `TicketStatusChanged` | Status transition |
| `TicketAssigned` | Agent assigned |
| `ReplyCreated` | Public reply added |
| `InternalNoteAdded` | Agent note added |
| `SlaBreached` | SLA deadline missed |
| `TicketEscalated` | Ticket escalated |
| `TicketResolved` | Ticket resolved |
| `TicketClosed` | Ticket closed |

```php
use Escalated\Laravel\Events\TicketCreated;

Event::listen(TicketCreated::class, function ($event) {
    // $event->ticket
});
```

[Full events documentation →](docs/events.md)

## 入站邮件

Escalated can create and reply to tickets from incoming emails. Supports **Mailgun**, **Postmark**, **AWS SES** webhooks, and **IMAP** polling as a fallback.

### 工作原理

1. An external email service receives an email at your support address (e.g., `support@yourapp.com`)
2. The service forwards the email to your application via webhook (or IMAP polling fetches it)
3. Escalated normalizes the payload into an `InboundMessage` DTO via the adapter
4. The `InboundEmailService` processes the message:
   - **Thread matching**: checks the subject for a ticket reference (e.g., `[ESC-00001]`), then checks `In-Reply-To` / `References` headers against stored message IDs
   - **Match found**: adds a reply to the existing ticket; reopens the ticket if it was resolved or closed
   - **No match**: creates a new ticket — if the sender is a registered user they become the requester, otherwise a guest ticket is created
5. Every inbound email is logged to `escalated_inbound_emails` for audit

### 启用入站邮件

```env
ESCALATED_INBOUND_EMAIL=true
ESCALATED_INBOUND_ADDRESS=support@yourapp.com
```

### 适配器设置

#### Mailgun

```env
ESCALATED_INBOUND_ADAPTER=mailgun
ESCALATED_MAILGUN_SIGNING_KEY=your-mailgun-signing-key
```

Configure a Mailgun Route to forward inbound emails to:

```
POST https://yourapp.com/support/inbound/mailgun
```

The signing key is in your Mailgun dashboard under **Settings > API Keys > HTTP Webhook Signing Key**. Requests are verified via HMAC-SHA256 signature.

#### Postmark

```env
ESCALATED_INBOUND_ADAPTER=postmark
ESCALATED_POSTMARK_INBOUND_TOKEN=your-postmark-inbound-token
```

Configure an Inbound Webhook in your Postmark server settings pointing to:

```
POST https://yourapp.com/support/inbound/postmark
```

The token is sent in the `X-Postmark-Token` header and verified on each request.

#### AWS SES

```env
ESCALATED_INBOUND_ADAPTER=ses
ESCALATED_SES_REGION=us-east-1
ESCALATED_SES_TOPIC_ARN=arn:aws:sns:us-east-1:123456789:your-topic
```

1. Configure SES to receive emails and publish to an SNS topic
2. Create an HTTPS subscription on the SNS topic pointing to:
   ```
   POST https://yourapp.com/support/inbound/ses
   ```
3. Escalated auto-confirms the SNS subscription and verifies message signatures using Amazon's certificate

#### IMAP (Fallback)

For providers without webhook support, poll via IMAP:

```env
ESCALATED_INBOUND_ADAPTER=imap
ESCALATED_IMAP_HOST=imap.gmail.com
ESCALATED_IMAP_PORT=993
ESCALATED_IMAP_ENCRYPTION=ssl
ESCALATED_IMAP_USERNAME=support@yourapp.com
ESCALATED_IMAP_PASSWORD=your-app-password
ESCALATED_IMAP_MAILBOX=INBOX
```

Schedule the poll command:

```php
Schedule::command('escalated:poll-imap')->everyMinute();
```

### Webhook URL

```
POST /{prefix}/inbound/{adapter}
```

Where `{prefix}` is your configured route prefix (default: `support`) and `{adapter}` is `mailgun`, `postmark`, or `ses`. These routes use the `api` middleware (no CSRF, no auth).

### 处理功能

- **Thread detection** via subject reference pattern (`[ESC-00001]`) and `In-Reply-To` / `References` headers
- **Guest tickets** for unknown senders — display name derived from email (e.g., `john.doe@example.com` → `John Doe`)
- **Subject sanitization** — strips `RE:`, `FW:`, `FWD:` prefixes (including stacked)
- **HTML fallback** — uses stripped HTML body when plain text is empty
- **Duplicate detection** — skips messages with duplicate `Message-ID` headers
- **Attachment handling** — stores attachments respecting `max_attachment_size_kb` and `max_attachments_per_reply`
- **Auto-reopen** — reopens resolved/closed tickets when a reply arrives via email
- **Audit logging** — every inbound email recorded in `escalated_inbound_emails` with status tracking

### 自定义适配器

Implement the `InboundAdapter` interface:

```php
use Escalated\Laravel\Mail\Adapters\InboundAdapter;
use Escalated\Laravel\Mail\InboundMessage;
use Illuminate\Http\Request;

class MyAdapter implements InboundAdapter
{
    public function parseRequest(Request $request): InboundMessage
    {
        return new InboundMessage(
            fromEmail: $request->input('from'),
            fromName: $request->input('name'),
            toEmail: $request->input('to'),
            subject: $request->input('subject'),
            bodyText: $request->input('text'),
            bodyHtml: $request->input('html'),
            messageId: $request->input('message_id'),
            inReplyTo: $request->input('in_reply_to'),
        );
    }

    public function verifyRequest(Request $request): bool
    {
        return $request->header('X-Secret') === config('services.my_adapter.secret');
    }
}
```

### 入站邮件环境变量

| Variable | Default | Description |
|----------|---------|-------------|
| `ESCALATED_INBOUND_EMAIL` | `false` | Enable inbound email |
| `ESCALATED_INBOUND_ADAPTER` | `mailgun` | Default adapter |
| `ESCALATED_INBOUND_ADDRESS` | `support@example.com` | Support email address |
| `ESCALATED_MAILGUN_SIGNING_KEY` | — | Mailgun webhook signing key |
| `ESCALATED_POSTMARK_INBOUND_TOKEN` | — | Postmark inbound token |
| `ESCALATED_SES_REGION` | `us-east-1` | AWS SES region |
| `ESCALATED_SES_TOPIC_ARN` | — | AWS SNS topic ARN |
| `ESCALATED_IMAP_HOST` | — | IMAP server hostname |
| `ESCALATED_IMAP_PORT` | `993` | IMAP server port |
| `ESCALATED_IMAP_ENCRYPTION` | `ssl` | IMAP encryption |
| `ESCALATED_IMAP_USERNAME` | — | IMAP username |
| `ESCALATED_IMAP_PASSWORD` | — | IMAP password |
| `ESCALATED_IMAP_MAILBOX` | `INBOX` | IMAP mailbox to poll |

## 路由

| Route | Method | Description |
|-------|--------|-------------|
| `/support` | GET | Customer ticket list |
| `/support/create` | GET | New ticket form |
| `/support/{ticket}` | GET | Ticket detail |
| `/support/guest/create` | GET | Guest ticket form |
| `/support/guest/{token}` | GET | Guest ticket view (magic link) |
| `/support/agent` | GET | Agent dashboard |
| `/support/agent/tickets` | GET | Agent ticket queue |
| `/support/agent/tickets/{ticket}` | GET | Agent ticket view |
| `/support/admin/reports` | GET | Admin reports |
| `/support/admin/departments` | GET | Department management |
| `/support/admin/sla-policies` | GET | SLA policy management |
| `/support/admin/escalation-rules` | GET | Escalation rule management |
| `/support/admin/tags` | GET | Tag management |
| `/support/admin/canned-responses` | GET | Canned response management |
| `/support/inbound/mailgun` | POST | Mailgun inbound webhook |
| `/support/inbound/postmark` | POST | Postmark inbound webhook |
| `/support/inbound/ses` | POST | SES/SNS inbound webhook |
| `/support/agent/tickets/bulk` | POST | Bulk actions on multiple tickets |
| `/support/agent/tickets/{ticket}/follow` | POST | Follow/unfollow a ticket |
| `/support/agent/tickets/{ticket}/macro` | POST | Apply a macro to a ticket |
| `/support/agent/tickets/{ticket}/presence` | POST | Update presence on a ticket |
| `/support/agent/tickets/{ticket}/pin/{reply}` | POST | Pin/unpin an internal note |
| `/support/{ticket}/rate` | POST | Submit satisfaction rating |

All routes use the configurable prefix (default: `support`). Inbound webhook routes use the `api` middleware (no auth, no CSRF).

## 插件 SDK

Escalated 支持使用 [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk) 构建的框架无关插件。插件用 TypeScript 编写一次，即可在所有 Escalated 后端上运行。

### 安装插件

The plugin bridge is built into `escalated-laravel` — no additional PHP package required. Install plugins and the runtime via npm:

```bash
npm install @escalated-dev/plugin-runtime
npm install @escalated-dev/plugin-slack
npm install @escalated-dev/plugin-jira
```

### 启用 SDK 插件

```php
// config/escalated.php
'plugins' => [
    'enabled'     => true,
    'sdk_enabled' => true,  // Enable the Node.js bridge
],
```

### 工作原理

SDK plugins run as a Node.js subprocess managed by `@escalated-dev/plugin-runtime`, communicating with Laravel over JSON-RPC 2.0 via stdio. The `escalated_do_action()` and `escalated_apply_filters()` helpers dual-dispatch to both legacy PHP plugins and new SDK plugins simultaneously — no changes to existing hook call sites.

### 构建自己的插件

```typescript
import { definePlugin } from '@escalated-dev/plugin-sdk'

export default definePlugin({
  name: 'my-plugin',
  version: '1.0.0',
  actions: {
    'ticket.created': async (event, ctx) => {
      ctx.log.info('New ticket!', event)
    },
  },
})
```

### 资源

- [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk) — 用于构建插件的 TypeScript SDK
- [Plugin Runtime](https://github.com/escalated-dev/escalated-plugin-runtime) — 插件运行时宿主
- [Plugin Development Guide](https://github.com/escalated-dev/escalated-docs) — 完整文档

See the detailed [Plugin Bridge](#plugin-bridge-sdk-plugins) section below for the full architecture, auto-generated routes, dual dispatch, and store documentation.

## 插件桥接（SDK 插件）

Escalated supports a second generation of plugins written in TypeScript using the `@escalated-dev/plugin-sdk`. These plugins run as a Node.js subprocess managed by `@escalated-dev/plugin-runtime` and communicate with Laravel over JSON-RPC 2.0 via stdio.

### 工作原理

```
Laravel (PHP)                     Plugin Runtime (Node.js)
┌──────────────────────┐  stdio   ┌──────────────────────┐
│ PluginBridge         │◄────────►│ @escalated-dev/       │
│  - spawns subprocess │  JSON-   │   plugin-runtime      │
│  - dispatches hooks  │  RPC 2.0 │  ┌────────────────┐   │
│  - handles ctx.*     │          │  │ Slack Plugin    │   │
│  - mounts routes     │          │  │ Jira Plugin     │   │
└──────────────────────┘          │  │ ...             │   │
                                  │  └────────────────┘   │
                                  └──────────────────────┘
```

The bridge spawns the runtime **lazily** on the first hook dispatch and keeps the process alive across requests (one long-lived subprocess per PHP-FPM worker). If the process crashes it is automatically restarted with exponential backoff.

### 环境要求

- Node.js 18+
- `@escalated-dev/plugin-runtime` installed in your project:

```bash
npm install @escalated-dev/plugin-runtime
```

Install any SDK plugins the same way:

```bash
npm install @escalated-dev/plugin-slack @escalated-dev/plugin-jira
```

### 启动序列

1. `EscalatedServiceProvider::boot()` calls `$bridge->boot()`
2. Bridge spawns `node node_modules/@escalated-dev/plugin-runtime/dist/index.js`
3. Protocol handshake confirms version compatibility
4. Bridge fetches the plugin manifest (pages, hooks, endpoints, webhooks)
5. Routes are registered in Laravel for plugin pages, API endpoints, and webhooks
6. Runtime is ready to receive hook dispatches

### 自动生成的路由

For each installed SDK plugin the bridge automatically registers:

| Category | URL Pattern | Auth |
|----------|-------------|------|
| Admin pages | `{prefix}/admin/plugins/{plugin}/{route}` | Admin |
| Data endpoints | `{prefix}/api/plugins/{plugin}/{path}` | Admin |
| Webhook endpoints | `{prefix}/webhooks/plugins/{plugin}/{path}` | None |

### 双重分派（向后兼容）

The existing `escalated_do_action()` and `escalated_apply_filters()` helper functions dispatch hooks to **both** old PHP plugins and new SDK plugins simultaneously. No changes are required to existing hook call sites.

```php
// This automatically dispatches to PHP plugins AND SDK plugins:
escalated_do_action('ticket.created', $ticket->toArray());

// Same for filters:
$channels = escalated_apply_filters('notification.channels', []);
```

### 插件存储

SDK plugins can persist data using `ctx.store`. This is backed by the `escalated_plugin_store` table:

```bash
php artisan vendor:publish --tag=escalated-migrations
php artisan migrate
```

### 配置

```php
// config/escalated.php
'plugins' => [
    'enabled'         => true,
    'sdk_enabled'     => true,      // Enable the Node.js bridge
    'runtime_command' => 'node node_modules/@escalated-dev/plugin-runtime/dist/index.js',
    'runtime_cwd'     => base_path(), // Working directory for the subprocess
],
```

### 编写 SDK 插件

See the [`@escalated-dev/plugin-sdk`](https://github.com/escalated-dev/plugin-sdk) package for the full TypeScript authoring API. A minimal plugin looks like:

```typescript
import { definePlugin } from '@escalated-dev/plugin-sdk'

export default definePlugin({
  name: 'my-plugin',
  version: '1.0.0',

  actions: {
    'ticket.created': async (event, ctx) => {
      const config = await ctx.config.all()
      // ... do something
    },
  },

  endpoints: {
    'GET /settings': { capability: 'manage_settings', handler: async (ctx) => {
      return await ctx.config.all()
    }},
  },
})
```

## 文档

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Customization](docs/customization.md)
- [Events](docs/events.md)
- [SLA Policies](docs/sla-policies.md)
- [Escalation Rules](docs/escalation-rules.md)
- [Hosting Modes](docs/hosting-modes.md)

## 测试

```bash
composer install
vendor/bin/pest
```

## 其他框架版本

- **[Escalated for Laravel](https://github.com/escalated-dev/escalated-laravel)** — Laravel Composer 包（当前页面）
- **[Escalated for Rails](https://github.com/escalated-dev/escalated-rails)** — Ruby on Rails 引擎
- **[Escalated for Django](https://github.com/escalated-dev/escalated-django)** — Django 可复用应用
- **[Escalated for AdonisJS](https://github.com/escalated-dev/escalated-adonis)** — AdonisJS v6 包
- **[Escalated for Filament](https://github.com/escalated-dev/escalated-filament)** — Filament v3 管理面板插件
- **[Shared Frontend](https://github.com/escalated-dev/escalated)** — Vue 3 + Inertia.js UI 组件

相同的架构、相同的 Vue UI、相同的三种托管模式 — 适用于每个主流后端框架。

## 许可证

MIT
