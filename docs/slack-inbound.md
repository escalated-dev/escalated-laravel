# Durable Slack inbound

The native Laravel receiver accepts authenticated Slack message events into a
database inbox, then a scheduled worker creates tickets and public replies.
It works with separate host/package databases and tenant mode. The generic
plugin runtime remains disabled in tenant mode; the native receiver does not
enable it or trust an inbound tenant header.

## Configure the host

Run the Slack inbox migration and configure `escalated.slack` in the host:

```php
'slack' => [
    'enabled' => true,
    'apps' => [
        'default' => [
            'app_id' => 'A123',
            'signing_secret' => env('SLACK_SIGNING_SECRET'),
            'channels' => [
                'T123' => [
                    'C123' => [
                        'tenant_id' => 'merchant-a', // Required with tenancy enabled.
                        'actor_id' => 'host-agent-key',
                        'department_id' => null,
                    ],
                ],
            ],
            'users' => [
                'T123' => [
                    'U123' => ['id' => 'host-requester-key'],
                    'U456' => ['name' => 'Recipient', 'email' => 'host-mapped@example.com'],
                ],
            ],
        ],
    ],
    'plugin_app' => 'default',
    'requests_per_minute' => 600,
    'max_attempts' => 8,
],
```

Use real Slack app/workspace/channel/user IDs. Requester mappings are trusted
host configuration: provision them from your identity/account system. Message
text never selects a requester, email, tenant, service agent or department.
A named contact creates no host login and receives no verified-email claim or
guest-access credential. The configured service actor must be an authorized
agent/admin; host users resolve on their own connection and must pass tenant
membership/reference checks. Replies require the mapped ticket requester or a
host user authorized by the ticket reply policy. A different mapped contact
cannot reply to another contact's ticket.

Subscribe the Slack app to the message events it needs and configure its Events
API URL as `/support/inbound/slack/default` on the host origin (adjust the support
prefix and app key). The app's signing secret authenticates URL verification too.
The receiver uses the [Slack signing protocol](https://docs.slack.dev/authentication/verifying-requests-from-slack/):
exact body bytes, HMAC-SHA256, constant-time comparison and a five-minute window.
It also checks the app ID and explicit workspace/channel mapping for messages.
Bot, hidden, edit/deletion and other subtype events are ignored. Attachments,
edits, deletions, Slack Connect identity discovery and automatic Slack user/email
lookup are not supported by this text-message adapter.

The native route resolves its tenant only after authentication. Exclude it from
host middleware that requires a browser session, supplies an unverified tenant,
or wraps the package connection in an outer transaction. Such a transaction
returns 503: an uncommitted write must not be acknowledged as durable. The route
also applies the configured per-minute IP budget; configure trusted proxies in
Laravel. Unknown/unconfigured apps or disabled/non-self-hosted mode reject use.

## Process and monitor the inbox

Run `php artisan escalated:slack:process` every minute. With
`escalated.scheduling.auto_register` enabled, Escalated registers it automatically.
In tenant mode this runs through the host tenant catalog:

```sh
php artisan escalated:tenant-run escalated:slack:process
php artisan escalated:tenant-run escalated:slack:process --tenant=merchant-a --arguments='{"--failed":true}'
php artisan escalated:tenant-run escalated:slack:process --tenant=merchant-a --arguments='{"--retry":42}'
```

For a single tenant installation, use `escalated:slack:process --failed` to list
failed receipt IDs without message bodies, and `--retry=42` after correcting the
host mapping/configuration. Limit each batch with `--limit` (default 100, maximum
1,000). Monitor failed receipts and the age of pending records. Temporary errors
retry with bounded backoff; after the configured attempt limit a receipt remains
failed for explicit recovery. Unmapped identities report `identity_not_mapped`.
No failure is acknowledged as a successfully created ticket.

HTTP 202 means the package database committed a receipt, not that processing has
finished. No ticket creation, external API call or queue execution occurs before
that acknowledgement. The inbox itself is the durable work queue, so a process
restart or unavailable external queue cannot lose an accepted message. Unique
event/message identities and row locks serialize redeliveries and concurrent
workers. A conflicting payload or changed tenant binding cannot overwrite an
existing receipt. Removing/reassigning a channel after acceptance makes pending
work fail closed; changing host identity mappings also affects subsequent work.

A root message creates one ticket with channel `slack`, escaped message text,
the mapped requester and origin metadata. A thread message becomes a public
reply on that mapped ticket. Replies arriving before their root are deferred for
up to a day, then remain failed for recovery. A thread whose original message was
a bot notification or predates this adapter has no inbound root mapping and is
not silently turned into another ticket. Deleting a ticket preserves its receipt
and thread identities so a retry cannot recreate deleted correspondence.

Ticket/reply/activity/thread writes and the processed receipt commit together.
Creation events run after commit, when listeners can see the complete mapping.
Listener failures after commit do not recreate the ticket/reply; external
notifications do not have a transactional outbox guarantee. Inbox payloads are
encrypted with the host application key and hidden from model serialization.
Retain that key for pending work, and include inbox payloads in the host's data
retention policy. Receipt identities remain stored for deduplication.

## Optional SDK plugin entry point

In single-tenant mode the authenticated Slack plugin may continue using
`/support/webhooks/plugins/slack/webhook`. Install the compatible SDK/runtime/host
HTTP contract first, configure the same app secret and routing in the native host
configuration above, and choose its app key with `slack.plugin_app`. The host now
subscribes to `slack.message.received`, independently verifies its original signed
bytes and returns a matching durable receipt. It ignores plugin-supplied tenant
or verification assertions. The plugin returns retryable 503 when the host does
not durably accept the message.

The native adapter persists `metadata.source = "slack"` and
`metadata.slack = {app, workspace, channel, thread_ts, event_id, user}`. Compatible
Slack plugin handlers can use that origin for host public replies and suppress
imported-message echoes. The host still owns outbound framework hook dispatch;
native tenant-mode outbound Slack delivery is not implemented here. Internal
notes must never be forwarded. This work does not publish npm/Composer packages,
configure a production Slack app, or introduce Microsoft Teams support.
