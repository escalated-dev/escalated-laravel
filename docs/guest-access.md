# Verified guest access

Guest ticket creation, live chat, and ticket lookup require proof of access to
the recipient's mailbox. This applies to the Laravel browser, mobile, and built-in
widget routes. Other Escalated backends must implement the same contract before
advertising the verification capability.

## Upgrade and host configuration

Deploy the shared frontend's verified guest forms before enabling these backend
routes, and run the package migrations. Existing `Guest/Create` and `Guest/Show`
pages are reused. `Guest/Create` receives `verification_url` and `lookup_url`;
widget configuration advertises `guest_verification_required: true`. Older
clients cannot create guest tickets without adding the email-code step.

Legacy permanent ticket and chat tokens no longer authorize requests. Existing
tickets remain intact. Recipients can verify their email and use their ticket
reference to obtain a new link. There is no fallback to email-only access or an
unsigned legacy token. Inbound email or agent-created records are not treated as
proof that the mailbox owner has authenticated to the public UI.

Configure working outbound Laravel mail and a shared cache with atomic locks
(for example Redis) across all application workers. Keep `APP_KEY` consistent
across those workers. The key protects stored email addresses, code digests and
authenticated guest grants. Configure HTTPS and trusted proxies normally. Web
and widget POST requests retain Laravel CSRF protection; mobile endpoints use
their existing API middleware. Cross-origin widget installation still requires a
host CORS/CSRF design; this feature does not release the separate web-widget
plugin or provide a blanket CORS exception.

`escalated.guest_access.ttl_minutes`
defaults to 1,440 minutes (24 hours), clamped to 5 minutes through 7 days.
Expired verification records are removed by the existing scheduled
`escalated:purge-expired` command. Use the package's tenant catalog and scheduled
command configuration when tenancy is enabled; public requests must resolve the
merchant through the host's `TenantResolver`, never a posted tenant ID.

## Email proof

Each surface exposes POST `verification` and POST `lookup` under its prefix:

| Surface | Prefix |
| --- | --- |
| Browser | `/support/guest` |
| Mobile | `/support/api/v1/mobile/guest` |
| Built-in widget | `/support/widget` |

The route prefixes can be customized through the existing package configuration.
Request a code with `{ "email": "recipient@example.com", "purpose": "ticket" }`.
The other supported purposes are `chat` and `lookup`. The 202 response contains
`verification_id`, `expires_in: 600` and a generic message. Only the email contains
the eight-digit code. Ticket/lookup requests require guest tickets to be enabled;
chat requests require chat to be enabled.

Send `verification_id` and `verification_code` with the corresponding operation.
Proof is bound to the tenant, normalized email and purpose. It expires after ten
minutes, permits at most five guesses, and can be consumed once. Consumption and
the authorized database operation commit together. Incorrect attempts remain
counted. A failed operation rolls back consumption so it can be retried.

Route-level IP budgets cover reads and writes across all three surfaces. In
addition, code delivery is limited to three requests per normalized email per
hour, shared across IPs, tenants and purposes. A 429 response includes
`Retry-After`. Changing an IP, token or route does not reset the email budget.

## Tickets and private grants

Browser creation retains `guest_name` and `guest_email`; mobile and widget
creation retain `name` and `email`. Add the proof fields to the existing ticket
payload. Browser creation redirects to the private ticket URL. Mobile creation
returns `data.guest_access_token`; widget creation returns `guest_access_token`.
The token is an opaque authenticated, encrypted, expiring grant. Do not parse it
or assume the old 64-character format.

Browser and mobile reads/replies use the grant in their existing token route.
Widget GET `tickets/{reference}` requires `Authorization: Bearer <grant>` and a
matching ticket reference; an email query parameter cannot authorize it. Guest
CSAT and attachment downloads enforce the same active grant. Internal notes
remain excluded. Responses use `Cache-Control: no-store` and
`Referrer-Policy: no-referrer`. Hosts should redact guest grant route segments and
authorization headers from access logs and analytics.

The database stores a random nonce hash rather than the bearer credential.
Renewal rotates the grant, invalidating earlier grants and attachment links.
Changing the ticket's guest email revokes its proof and grants, including model
`updateQuietly` calls. Trusted host code can call
`app(GuestAccess::class)->revoke($ticket)` to revoke access explicitly. Raw SQL
updates bypass model protections and should not be used to change guest identity.
`GuestAccess::issue()` is a trusted service entry point, not an alternative to
consuming mailbox proof.

## Tracking lookup and renewal

After requesting a code with purpose `lookup`, POST `lookup` with `email`,
`reference`, `verification_id` and `verification_code`. `reference` can be the
Escalated ticket reference or a host-assigned `Ticket.external_reference`:

```php
$ticket->update(['external_reference' => $shipment->tracking_number]);
```

The host supplies this value after authorizing the shipment/account association.
Anonymous creation cannot assign it. A reference alone does not authorize access;
lookup also requires a matching verified recipient email within the current
tenant. The response is `{ "data": [...] }`, with each match containing
`reference`, `subject`, `guest_access_token` and `expires_at`. At most 20 matching
tickets are returned. The result may be empty and proof is still consumed.

This finds existing tickets linked by the host. It does not call a carrier API or
return shipment data. Host-user requester records without a guest email are not
implicitly exposed by matching a host user's email address.

## Live chat and transaction behavior

POST `chat/start` accepts chat-purpose proof. Its `id` and `session_id` are the
same opaque chat grant; `expires_at` describes its lifetime. The existing
`chat/{sessionId}/message`, `/typing`, `/end` and `/rate` operations require it.
GET `/messages` returns the latest 100 public messages, agent and typing state;
POST `/messages` is an alias for the existing singular message route. Chat grants
cannot authorize ticket routes, and ticket grants cannot authorize chat routes.
Live chats must be restarted after expiry or grant rotation.

Guest ticket/contact/attachments and proof consumption are one package-database
transaction. Files written before a later upload failure are removed. Creation
events and chat routing run after commit with the captured tenant context, so
listeners see the completed verified aggregate. This does not make external
notifications transactional: a listener can fail after commit, and the operation
does not supply an idempotency key or durable outbox. An enclosing host transaction
that later rolls back does not itself provide filesystem rollback hooks.
