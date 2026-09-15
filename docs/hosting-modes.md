# Hosting Modes

Escalated supports three hosting modes, configured via `config('escalated.mode')`.

## Self-Hosted (Default)

```php
'mode' => 'self-hosted',
```

Everything runs locally. All ticket data is stored in your database. No external API calls. Full autonomy.

**Best for:** Apps that want complete control over their support data.

## Synced

```php
'mode' => 'synced',
'hosted' => [
    'api_url' => 'https://cloud.escalated.dev/api/v1',
    'api_key' => 'your-api-key',
],
```

Extends self-hosted. After every write operation, events are synced server-to-server to `cloud.escalated.dev`. The cloud stores a projection for:

- Unified inbox across multiple apps
- Cross-app visibility
- Advanced SLA tracking
- Cloud-based agent portal

If the cloud is unreachable, the app continues working normally. Events are delivered by the `SyncEventToCloud` queued job (5 attempts with exponential backoff, `ESCALATED_SYNC_QUEUE` picks a queue) and each carries a stable `event_id` so the cloud ignores redeliveries.

Sync is two-way. Agent actions taken in the cloud portal come back as signed webhooks to `POST /escalated/cloud/webhook`; set `ESCALATED_CLOUD_SIGNING_SECRET` to the signing secret minted for the connected site on cloud.escalated.dev (Sites → Generate Secret). The receiver applies `ticket.updated` and `ticket.status_changed` to the local ticket through the normal driver, so listeners, notifications and workflows fire as for a local edit.

```bash
# .env
ESCALATED_MODE=synced
ESCALATED_API_URL=https://cloud.escalated.dev/api/v1
ESCALATED_API_KEY=your-sync-token
ESCALATED_CLOUD_SIGNING_SECRET=your-webhook-signing-secret
```

**Best for:** Companies with multiple apps wanting a unified support view.

## Cloud

```php
'mode' => 'cloud',
'hosted' => [
    'api_url' => 'https://cloud.escalated.dev/api/v1',
    'api_key' => 'your-api-key',
],
```

All ticket operations are proxied server-to-server to the cloud API. The local database is empty (or acts as a read cache). Your app handles auth and renders UI, but all CRUD flows through the cloud.

Each app is identified by its API key, enabling multi-domain support.

**Best for:** Apps that want zero local storage overhead.

## Architecture

```
Browser → Host App Backend → TicketManager → Driver
                                               ├── LocalDriver  (DB only)
                                               ├── SyncedDriver (DB + Cloud sync)
                                               └── CloudDriver  (Cloud proxy)
```

The UI always talks to the host app's backend — never directly to the cloud. All cloud communication is server-to-server using API credentials.

## Switching Modes

Switching is just a config change. No code changes needed:

```bash
# .env
ESCALATED_MODE=synced
ESCALATED_API_URL=https://cloud.escalated.dev/api/v1
ESCALATED_API_KEY=your-key-here
```
