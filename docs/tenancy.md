# Host account isolation

Tenancy is opt-in and currently supports **self-hosted mode**. Each merchant-owned
table carries `tenant_id`; tickets, replies, attachments, settings, roles, tokens,
reports and scheduled work require a current tenant when it is enabled. A missing
tenant denies access. Staff access requires a tenant-local seat as well as the host
agent/admin gate (see [Staff seats](#staff-seats)).
Host users stay on their own database connection.

Laravel 11 requires at least 11.39.0 for the package's worker completion,
middleware priority, related pivot model and translation loader APIs. Laravel 12
and 13 are supported. CI tests the exact minimum as well as the latest versions.
The historical 11.39 CI fixture uses Symfony Console 7.3: that old framework's
console container integration predates Symfony 7.4. Prefer a current patched host
framework rather than using the historical fixture as a deployment template.

## Host contracts

Configure `escalated.tenancy.resolver` with a class implementing
`Escalated\Laravel\Contracts\TenantResolver`:

| Method | Host responsibility |
| --- | --- |
| `resolve(Request)` | Return the account selected by trusted host middleware/session/domain routing, or `null`. Never trust a submitted account ID as authorization. |
| `canAccess(Model $user, string $tenantId)` | Check current membership for the authenticated user, including revoked/inactive memberships. |
| `canReference(Model $model, string $tenantId)` | Check visibility of a host user or linked host entity (shipment/order/account) in that account. Deny unknown model types. |
| `scope(Builder $query, string $tenantId)` | Restrict host entity discovery on that model's own connection. Apply the same account rules as `canReference`; unknown models should match nothing. |
| `isAgent(Model $user, string $tenantId)` | Return whether the user holds an agent seat in that account. Include account admins who answer tickets. |
| `isAdmin(Model $user, string $tenantId)` | Return whether the user administers that account. |
| `isPlatformAdmin(Model $user)` | Authorize installation-wide administration separately from merchant admin access. |

The default resolver denies everything. Tenant identifiers are case-sensitive,
nonempty strings of at most 128 bytes; whitespace around IDs and control characters
are rejected. Use a stable account key. Do not use a person's current role or a
mutable account name as that key.

### Staff seats

The `escalated-agent` / `escalated-admin` gates (or the gates named in
`escalated.authorization`) usually read global host flags such as `is_agent`. Those
flags cannot say *which* account a person staffs. In tenant mode every package staff
check (agent/admin middleware, ticket and admin policies, API token abilities, agent
directories and assignment, broadcast channels and private attachments) requires all
of:

1. the host gate,
2. current membership (`canAccess`), and
3. the seat for the current account: `isAgent` for agent surfaces, `isAdmin` for admin
   surfaces.

A person who is staff at one merchant and a customer at another is therefore only a
customer at the second. The default resolver, and any resolver extending
`UnconfiguredTenantResolver` without overriding these methods, returns `false`, so
staff access fails closed until the host provides seats. Single-account installations
(tenancy disabled) use the gates unchanged. Host code that needs the same decision can
call `Escalated\Laravel\Support\StaffAccess::isAgent($user)` / `isAdmin($user)`
inside a resolved account context.

For example, a host with `User::accounts()` would check membership using the user's
host-database relation and constrain user discovery with `whereHas('accounts',
fn ($accounts) => $accounts->whereKey($tenantId))`. A host Shipment model may instead
use its `account_id` column. Implement these rules for every allowed host model;
Escalated does not infer membership from an email address.

List fully qualified account-selection middleware classes in
`escalated.tenancy.middleware`. Package routes order them after session/authentication
and before tenant resolution and route-model binding. Public guest/widget/inbound
requests also require a trusted account mapping, usually an allowlisted host domain
or integration identity. A posted `tenant_id` does not select the account.

Package API tokens retain their issuing tenant. The opaque token lookup establishes
that tenant before loading its owner, then rechecks membership, expiry and abilities.
A conflicting account selected by the host is rejected. Mobile registration and
automatic creation of new host identities are unavailable in tenant mode; provision
host accounts and users through the host application.

## Upgrading a single-account installation

Take a database/storage backup and pause HTTP writers, queue workers and the
scheduler for the final migration/backfill. Deploy the shared frontend with support
for `escalated.broadcasting.channel_prefix` before enabling tenant broadcasts.

1. Keep `ESCALATED_TENANCY_ENABLED=false` and run package migrations. Configure the
   host resolver so all existing host identities can be validated for the target
   account.
2. Run `php artisan escalated:tenant-backfill ACCOUNT` for a dry run. It assigns all
   legacy rows inside a transaction, checks local/host associations, then rolls back.
3. With writers paused, run
   `php artisan escalated:tenant-backfill ACCOUNT --apply --writers-paused`.
4. Enable `ESCALATED_TENANCY_ENABLED=true`, refresh the host's configuration cache,
   provision required accounts, and restart workers before resuming traffic.

Backfill assigns a **whole legacy installation to one account**. It refuses an
installation that already has any assigned rows; it cannot repartition mixed customer
history or move records between accounts. Invalid references roll back every table.
Soft-deleted local records remain valid historical references; host membership is
still checked. The migration refuses rollback while assigned tenant data exists.
Changing the configuration flag back to false would remove isolation and is not a
safe rollback for a multi-account installation.

`php artisan escalated:tenant-provision ACCOUNT` creates account defaults and the
standard roles. It preserves customized setting values and never copies another
account's credentials. Re-provisioning refreshes standard role permission mappings.
Host membership and the account's agent/admin seats must already exist before staff
access can succeed.

## Background and maintenance work

Implement `Contracts\TenantCatalog::tenantIds(): iterable` and set
`escalated.tenancy.catalog` to enumerate active account IDs from the host database.
The automatically registered scheduler runs each maintenance command through this
catalog. Missing catalog configuration is an error.

```sh
php artisan escalated:tenant-run escalated:check-sla
php artisan escalated:tenant-run escalated:close-resolved --tenant=ACCOUNT
```

The wrapper accepts an allowlist of package maintenance commands and an optional
`--arguments` JSON object. A failing account returns an overall failure while other
accounts continue. Explicit account selection must be present in the host catalog.
Custom host CLI integrations can establish a trusted context directly:

```php
app(\Escalated\Laravel\Tenancy\TenantContext::class)->run($accountId, function () {
    // Query Escalated models or dispatch work for this account.
});
```

Queue payloads capture the tenant and establish it before Eloquent restores models.
Host jobs without metadata receive no tenant. Delayed notifications recheck recipient
membership and ticket permissions before sending. Keep queue storage and failed-job
payloads under trusted operational control, as for any Laravel serialized job.

Use `escalated:tenant-retry` / `escalated:tenant-retry-batch` in place of the stock retry
commands for tenant jobs: retrying can deserialize models before the job is queued.
Sync and supported deferred queues preserve dispatch-time context, including
after-commit dispatch. For after-response work, use
`app(TenantDispatch::class)->afterResponse($job)` with the fully qualified
`Escalated\Laravel\Tenancy\TenantDispatch` class. Bare `dispatchAfterResponse` does
not capture Escalated context. Background subprocess queues are rejected for tenant
work; use a durable queue, sync or the supported deferred adapter.

## Broadcasts, presence and attachments

Events use `escalated.tenants.<sha256-account-id>` as the channel prefix. The prefix
is exposed in Inertia's `escalated.broadcasting.channel_prefix` and the API realtime
configuration. The shared frontend re-subscribes on account changes and discards old
socket callbacks and polling responses. Legacy global channels deny access while
tenancy is enabled.

Channel callbacks work through the host's existing `/broadcasting/auth` route.
That route must run the host's session/authentication and trusted account-selection
middleware too. Callbacks resolve the account independently of the requested channel,
verify membership and ticket visibility, and return only ID/name for agent presence.
Requesters can subscribe to authorized private ticket updates, never agent presence.
The host remains responsible for terminating active socket sessions when access is
revoked; a completed WebSocket subscription is not reauthorized by Laravel on every
event.

Presence cache keys are account-specific. Private attachment downloads require the
current account, ticket authorization and a valid expiring signature. Existing public
storage copies require the separate [attachment migration](private-attachments.md);
adding a tenant column does not remove already-public objects or CDN copies.

## Current limits

- Cloud/synced modes do not propagate tenant context and are unsupported.
- PHP and SDK plugin execution is disabled in tenant mode until plugin lifecycle
  and process state support account isolation. Platform operators can manage
  installation configuration; merchant admins cannot change the database connection
  or run installation-wide plugin code.
- The host must configure guest/inbound account routing. Email verification,
  expiring guest access and external-reference lookup are separate integrations;
  enabling tenancy does not provide them.
- Raw SQL, host-written queries that bypass the package builders, and host plugin
  code remain trusted application code. Use package models or `Escalated::query`
  inside a resolved context. `Escalated::db()` is an explicit infrastructure escape
  hatch used by schema/upgrade code, not a tenant-aware query builder.
  Eloquent's low-level `getQuery()`, `toBase()` and `fromQuery()` also expose raw
  query/SQL access; do not use them to construct host integration queries. Use
  `Escalated::query(Escalated::table('tickets'))` for tenant-aware raw queries.

Before enabling production traffic, validate the host resolver against two accounts,
including shared agents, revoked memberships, signed downloads, queues, scheduled
work and the host broadcast authentication route.
