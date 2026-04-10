<p align="center">
  <a href="README.ar.md">العربية</a> •
  <a href="README.de.md">Deutsch</a> •
  <a href="../../README.md">English</a> •
  <a href="README.es.md">Español</a> •
  <a href="README.fr.md">Français</a> •
  <b>Italiano</b> •
  <a href="README.ja.md">日本語</a> •
  <a href="README.ko.md">한국어</a> •
  <a href="README.nl.md">Nederlands</a> •
  <a href="README.pl.md">Polski</a> •
  <a href="README.pt-BR.md">Português (BR)</a> •
  <a href="README.ru.md">Русский</a> •
  <a href="README.tr.md">Türkçe</a> •
  <a href="README.zh-CN.md">简体中文</a>
</p>

# Escalated for Laravel

[![Tests](https://github.com/escalated-dev/escalated-laravel/actions/workflows/laravel.yml/badge.svg)](https://github.com/escalated-dev/escalated-laravel/actions/workflows/laravel.yml)
[![Laravel](https://img.shields.io/badge/laravel-11.x--13.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/php-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Un sistema di ticket di supporto completo e integrabile per Laravel. Aggiungilo a qualsiasi app — ottieni un helpdesk completo con tracciamento SLA, regole di escalation, flussi di lavoro degli agenti e un portale clienti. Nessun servizio esterno richiesto.

> **[escalated.dev](https://escalated.dev)** — Scopri di più, guarda le demo e confronta le opzioni Cloud vs Self-Hosted.

**Tre modalità di hosting.** Esecuzione completamente self-hosted, sincronizzazione con un cloud centrale per la visibilità multi-app, o proxy di tutto verso il cloud. Cambio modalità con una singola modifica alla configurazione.

## Funzionalità

- **Ciclo di vita del ticket** — Creare, assegnare, rispondere, risolvere, chiudere, riaprire con transizioni di stato configurabili
- **Motore SLA** — Obiettivi di risposta e risoluzione per priorità, calcolo delle ore lavorative, rilevamento automatico delle violazioni
- **Regole di escalation** — Regole basate su condizioni che escalano, ripriorizzano, riassegnano o notificano automaticamente
- **Dashboard dell'agente** — Coda ticket con filtri, azioni di massa, note interne, risposte predefinite
- **Portale clienti** — Creazione ticket self-service, risposte e tracciamento dello stato
- **Pannello di amministrazione** — Gestire reparti, policy SLA, regole di escalation, tag e visualizzare report
- **Allegati** — Upload drag-and-drop con archiviazione configurabile e limiti di dimensione
- **Timeline delle attività** — Log di audit completo di ogni azione su ogni ticket
- **Notifiche email** — Notifiche configurabili per evento con supporto webhook
- **Routing per reparto** — Organizzare gli agenti in reparti con assegnazione automatica (round-robin)
- **Sistema di tagging** — Categorizzare i ticket con tag colorati
- **Ticket ospiti** — Invio anonimo di ticket con accesso tramite link magico via token ospite
- **Email in entrata** — Creare e rispondere ai ticket via email (Mailgun, Postmark, AWS SES, IMAP)
- **Inertia.js + Vue 3 UI** — Frontend condiviso tramite [`@escalated-dev/escalated`](https://github.com/escalated-dev/escalated)
- **Divisione ticket** — Dividere una risposta in un nuovo ticket autonomo preservando il contesto originale
- **Ticket snooze** — Sospendi i ticket con preimpostazioni (1h, 4h, domani, prossima settimana); il comando Artisan `escalated:wake-snoozed-tickets` li riattiva automaticamente
- **Viste salvate / code personalizzate** — Salvare, denominare e condividere preset di filtri come viste ticket riutilizzabili
- **Embeddable support widget** — Widget leggero `<script>` servito tramite le route `/support/widget/*` con ricerca KB, modulo ticket e verifica stato
- **Threading email** — Le email in uscita includono gli header `In-Reply-To` e `References` per un threading corretto nei client di posta
- **Template email personalizzati** — Logo, colore primario e testo del footer configurabili per tutte le email in uscita
- **Real-time broadcasting** — Broadcasting opzionale tramite Pusher, Reverb o Soketi con fallback automatico al polling
- **Toggle base di conoscenza** — Abilitare o disabilitare la base di conoscenza pubblica dalle impostazioni admin
- **CI: Laravel Pint** — Applicazione automatica dello stile del codice ad ogni pull request

## Requisiti

- PHP 8.2+
- Laravel 11.x, 12.x, or 13.x
- Node.js 18+ (per le risorse frontend)

## Avvio Rapido

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

## Integrazione Frontend

Escalated ships a Vue component library and default pages via the [`@escalated-dev/escalated`](https://github.com/escalated-dev/escalated) npm package.

### 1. Contenuto Tailwind

Aggiungi il pacchetto Escalated alla configurazione `content` di Tailwind affinché le sue classi non vengano rimosse:

```js
// tailwind.config.js
content: [
    // ... your existing paths
    './node_modules/@escalated-dev/escalated/src/**/*.vue',
],
```

### 2. Risolutore di Pagine

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

### 3. Temi (Opzionale)

Registra l'`EscalatedPlugin` per renderizzare le pagine Escalated all'interno del layout della tua app — nessuna duplicazione di pagine necessaria:

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

### Proprietà CSS Personalizzate

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

### Componenti Disponibili

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

### Props Inertia Condivisi

Escalated automatically shares data to all Inertia pages via `page.props.escalated`:

```js
page.props.escalated = {
    prefix: 'support',     // Route prefix from config
    is_agent: true,        // Current user can access agent views
    is_admin: false,       // Current user can access admin views
}
```

Use these to conditionally show nav links or restrict UI elements.

## Modalità di Hosting

### Self-Hosted (predefinito)

Tutto rimane nel tuo database. Nessuna chiamata esterna. Piena autonomia.

```php
// config/escalated.php
'mode' => 'self-hosted',
```

### Sincronizzato

Database locale + sincronizzazione automatica con `cloud.escalated.dev` per una casella di posta unificata su più app. Se il cloud non è raggiungibile, la tua app continua a funzionare — gli eventi vengono messi in coda e riprovati.

```php
'mode' => 'synced',
'hosted' => [
    'api_url' => 'https://cloud.escalated.dev/api/v1',
    'api_key' => env('ESCALATED_API_KEY'),
],
```

### Cloud

Tutti i dati dei ticket vengono inviati all'API cloud. La tua app gestisce l'autenticazione e renderizza l'interfaccia, ma l'archiviazione risiede nel cloud. Supporta più domini per chiave API.

```php
'mode' => 'cloud',
```

Tutte e tre le modalità condividono gli stessi controller, l'interfaccia e la logica di business. Il pattern driver gestisce il resto.

## Pubblicazione Risorse

```bash
# Email templates
php artisan vendor:publish --tag=escalated-views

# Config file
php artisan vendor:publish --tag=escalated-config

# Database migrations
php artisan vendor:publish --tag=escalated-migrations
```

## Pianificazione

Aggiungi questi al tuo scheduler per l'automazione SLA e delle escalation:

```php
// app/Console/Kernel.php or routes/console.php
Schedule::command('escalated:check-sla')->everyMinute();
Schedule::command('escalated:evaluate-escalations')->everyFiveMinutes();
Schedule::command('escalated:close-resolved')->daily();
Schedule::command('escalated:purge-activities')->weekly();
Schedule::command('escalated:poll-imap')->everyMinute(); // Only if using IMAP adapter
```

## Configurazione

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

## Eventi

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

## Email in Entrata

Escalated can create and reply to tickets from incoming emails. Supports **Mailgun**, **Postmark**, **AWS SES** webhooks, and **IMAP** polling as a fallback.

### Come Funziona

1. An external email service receives an email at your support address (e.g., `support@yourapp.com`)
2. The service forwards the email to your application via webhook (or IMAP polling fetches it)
3. Escalated normalizes the payload into an `InboundMessage` DTO via the adapter
4. The `InboundEmailService` processes the message:
   - **Thread matching**: checks the subject for a ticket reference (e.g., `[ESC-00001]`), then checks `In-Reply-To` / `References` headers against stored message IDs
   - **Match found**: adds a reply to the existing ticket; reopens the ticket if it was resolved or closed
   - **No match**: creates a new ticket — if the sender is a registered user they become the requester, otherwise a guest ticket is created
5. Every inbound email is logged to `escalated_inbound_emails` for audit

### Abilitare Email in Entrata

```env
ESCALATED_INBOUND_EMAIL=true
ESCALATED_INBOUND_ADDRESS=support@yourapp.com
```

### Configurazione dell'Adattatore

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

### URL del Webhook

```
POST /{prefix}/inbound/{adapter}
```

Where `{prefix}` is your configured route prefix (default: `support`) and `{adapter}` is `mailgun`, `postmark`, or `ses`. These routes use the `api` middleware (no CSRF, no auth).

### Funzionalità di Elaborazione

- **Thread detection** via subject reference pattern (`[ESC-00001]`) and `In-Reply-To` / `References` headers
- **Guest tickets** for unknown senders — display name derived from email (e.g., `john.doe@example.com` → `John Doe`)
- **Subject sanitization** — strips `RE:`, `FW:`, `FWD:` prefixes (including stacked)
- **HTML fallback** — uses stripped HTML body when plain text is empty
- **Duplicate detection** — skips messages with duplicate `Message-ID` headers
- **Attachment handling** — stores attachments respecting `max_attachment_size_kb` and `max_attachments_per_reply`
- **Auto-reopen** — reopens resolved/closed tickets when a reply arrives via email
- **Audit logging** — every inbound email recorded in `escalated_inbound_emails` with status tracking

### Adattatore Personalizzato

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

### Variabili d'Ambiente per Email in Entrata

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

## Percorsi

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

## SDK Plugin

Escalated supporta plugin indipendenti dal framework costruiti con il [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk). I plugin vengono scritti una volta in TypeScript e funzionano su tutti i backend Escalated.

### Installazione dei Plugin

The plugin bridge is built into `escalated-laravel` — no additional PHP package required. Install plugins and the runtime via npm:

```bash
npm install @escalated-dev/plugin-runtime
npm install @escalated-dev/plugin-slack
npm install @escalated-dev/plugin-jira
```

### Abilitazione Plugin SDK

```php
// config/escalated.php
'plugins' => [
    'enabled'     => true,
    'sdk_enabled' => true,  // Enable the Node.js bridge
],
```

### Come Funziona

SDK plugins run as a Node.js subprocess managed by `@escalated-dev/plugin-runtime`, communicating with Laravel over JSON-RPC 2.0 via stdio. The `escalated_do_action()` and `escalated_apply_filters()` helpers dual-dispatch to both legacy PHP plugins and new SDK plugins simultaneously — no changes to existing hook call sites.

### Creare il Proprio Plugin

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

### Risorse

- [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk) — SDK TypeScript per creare plugin
- [Plugin Runtime](https://github.com/escalated-dev/escalated-plugin-runtime) — Host runtime per i plugin
- [Plugin Development Guide](https://github.com/escalated-dev/escalated-docs) — Documentazione completa

See the detailed [Plugin Bridge](#plugin-bridge-sdk-plugins) section below for the full architecture, auto-generated routes, dual dispatch, and store documentation.

## Bridge Plugin (Plugin SDK)

Escalated supports a second generation of plugins written in TypeScript using the `@escalated-dev/plugin-sdk`. These plugins run as a Node.js subprocess managed by `@escalated-dev/plugin-runtime` and communicate with Laravel over JSON-RPC 2.0 via stdio.

### Come Funziona

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

### Requisiti

- Node.js 18+
- `@escalated-dev/plugin-runtime` installed in your project:

```bash
npm install @escalated-dev/plugin-runtime
```

Install any SDK plugins the same way:

```bash
npm install @escalated-dev/plugin-slack @escalated-dev/plugin-jira
```

### Sequenza di Avvio

1. `EscalatedServiceProvider::boot()` calls `$bridge->boot()`
2. Bridge spawns `node node_modules/@escalated-dev/plugin-runtime/dist/index.js`
3. Protocol handshake confirms version compatibility
4. Bridge fetches the plugin manifest (pages, hooks, endpoints, webhooks)
5. Routes are registered in Laravel for plugin pages, API endpoints, and webhooks
6. Runtime is ready to receive hook dispatches

### Percorsi Autogenerati

For each installed SDK plugin the bridge automatically registers:

| Category | URL Pattern | Auth |
|----------|-------------|------|
| Admin pages | `{prefix}/admin/plugins/{plugin}/{route}` | Admin |
| Data endpoints | `{prefix}/api/plugins/{plugin}/{path}` | Admin |
| Webhook endpoints | `{prefix}/webhooks/plugins/{plugin}/{path}` | None |

### Dispatch Duale (Compatibilità Retroattiva)

The existing `escalated_do_action()` and `escalated_apply_filters()` helper functions dispatch hooks to **both** old PHP plugins and new SDK plugins simultaneously. No changes are required to existing hook call sites.

```php
// This automatically dispatches to PHP plugins AND SDK plugins:
escalated_do_action('ticket.created', $ticket->toArray());

// Same for filters:
$channels = escalated_apply_filters('notification.channels', []);
```

### Store dei Plugin

SDK plugins can persist data using `ctx.store`. This is backed by the `escalated_plugin_store` table:

```bash
php artisan vendor:publish --tag=escalated-migrations
php artisan migrate
```

### Configurazione

```php
// config/escalated.php
'plugins' => [
    'enabled'         => true,
    'sdk_enabled'     => true,      // Enable the Node.js bridge
    'runtime_command' => 'node node_modules/@escalated-dev/plugin-runtime/dist/index.js',
    'runtime_cwd'     => base_path(), // Working directory for the subprocess
],
```

### Scrivere Plugin SDK

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

## Documentazione

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Customization](docs/customization.md)
- [Events](docs/events.md)
- [SLA Policies](docs/sla-policies.md)
- [Escalation Rules](docs/escalation-rules.md)
- [Hosting Modes](docs/hosting-modes.md)

## Test

```bash
composer install
vendor/bin/pest
```

## Disponibile Anche Per

- **[Escalated for Laravel](https://github.com/escalated-dev/escalated-laravel)** — Pacchetto Laravel Composer (sei qui)
- **[Escalated for Rails](https://github.com/escalated-dev/escalated-rails)** — Motore Ruby on Rails
- **[Escalated for Django](https://github.com/escalated-dev/escalated-django)** — App Django riutilizzabile
- **[Escalated for AdonisJS](https://github.com/escalated-dev/escalated-adonis)** — Pacchetto AdonisJS v6
- **[Escalated for Filament](https://github.com/escalated-dev/escalated-filament)** — Plugin pannello admin Filament v3
- **[Shared Frontend](https://github.com/escalated-dev/escalated)** — Componenti UI Vue 3 + Inertia.js

Stessa architettura, stessa interfaccia Vue, stesse tre modalità di hosting — per ogni framework backend principale.

## Licenza

MIT
