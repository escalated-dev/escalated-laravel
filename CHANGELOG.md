# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Upgrading
- **Roles & permissions is now an install-level switch, off by default** (`escalated.access.roles_permissions`, env `ESCALATED_ROLES_PERMISSIONS`; ADR 2026-10-29-configurable-access-features). Roles were stored but never enforced, so off is how every install already behaved, with two visible differences:
  - While it is off, the roles screens (`{prefix}/admin/roles*`) answer 404.
  - While it is off, the newsletter screens no longer check `newsletters.manage` / `newsletters.send`; the admin gate decides, like every other screen. Installs that used those permissions to restrict admins must turn the switch on.
  To enforce roles: seed the permissions (`php artisan db:seed --class="Escalated\Laravel\Database\Seeders\PermissionSeeder"`), give every staff user a role (`php artisan escalated:roles:assign you@example.com admin`), then set `ESCALATED_ROLES_PERMISSIONS=true`. Passing the admin gate grants no permissions by itself, so an admin without a role is refused everywhere but their own two-factor screen. See `docs/access-control.md`.
- **Two-factor authentication is now an install-level switch, off by default** (`escalated.access.two_factor`, env `ESCALATED_TWO_FACTOR`). Enrolment was possible but no login was ever challenged. While it is off, the two-factor screen and its setup/confirm/disable routes answer 404 (they answered 200 before) and nobody is challenged; enrolments are kept. To enforce it, run `php artisan migrate` (adds the nullable `last_used_step` column to `two_factor`), then set `ESCALATED_TWO_FACTOR=true`; users who already enrolled are challenged from their next visit. See `docs/access-control.md`.
- **Single sign-on is now an install-level switch, off by default** (`escalated.access.sso`, env `ESCALATED_SSO`). SSO settings were saved but nothing signed anyone in. While it is off, the SSO settings screen (`escalated.admin.settings.sso*`) answers 404 (it answered 200 before) and so does every sign-in route; stored settings are kept. To use it, run `php artisan migrate` (creates `escalated_sso_message_ids`), set `ESCALATED_SSO=true`, give the IdP the endpoints from the SSO settings page, and decide on `escalated.sso.create_users` / `sync_roles` (both default off). See `docs/access-control.md`.
- `SsoService::validateSamlAssertion()` is replaced by `validateSamlResponse()`, and `validateJwtToken()` now requires `exp`, `aud` and a single-use `jti` and returns `{email, name, role}` without `claims`. Neither was called anywhere in the package; hosts that called them directly must update.
- The two-factor screen (`escalated.admin.two-factor.*`, same URLs) is now open to every staff user, not only admins, since it manages the signed-in user's own account.
- The shared `escalated.permissions` prop is `['*']` for staff while the switch is off (it was the user's role slugs, which nothing enforced), and `escalated.features.roles_permissions` tells the frontend whether roles are on.

### Added
- **Roles & permissions are enforced when switched on.** Every admin, agent and staff API route needs its permission from the user's roles as well as the host gate (`EnforceRoutePermissions`, map in `src/Support/RoutePermissions.php`; unmapped routes are refused, and a test checks every route is mapped). Status changes to `resolved`/`closed`/`reopened` also need `ticket.close`, bulk actions need each action's permission, and the staff API checks the token owner's permissions. Failing answers 403.
- `php artisan escalated:roles:assign {user} {role=admin}` gives a user a role by email or ID.
- **Two-factor is enforced when switched on.** A staff user with a confirmed enrolment must pass `Auth/TwoFactorChallenge` (`GET|POST {prefix}/two-factor/challenge`, throttled to 5 a minute) with a TOTP or single-use recovery code before any admin or agent route, once per login. A TOTP code cannot be used twice. `escalated.two_factor.required` (`ESCALATED_TWO_FACTOR_REQUIRED`) or the `two_factor_required` setting makes every staff user enrol first. `php artisan escalated:two-factor:reset {user}` clears an enrolment. The page props are `action` and `error`; shared props gain `features.two_factor` and `two_factor_required`, and `Admin/Settings/TwoFactor` gains `required`.

- **Single sign-on works when switched on.** `GET {prefix}/sso/login` starts sign-in with the provider chosen in the admin. SAML (`onelogin/php-saml`, strict) requires a signed assertion from the configured certificate and checks Issuer, Audience, Destination, Recipient, validity windows, InResponseTo against requests this install issued (single use) and assertion replay. OAuth 2 / OIDC (`league/oauth2-client`) uses a session-bound `state` and PKCE S256 and reads the userinfo endpoint. JWT (`firebase/php-jwt`) pins the configured algorithm and requires `exp`, `aud` and a single-use `jti`. Users are matched by email; `escalated.sso.create_users` creates unknown ones and `escalated.sso.sync_roles` maps the role attribute to an Escalated role. New routes `escalated.sso.login`, `.saml.metadata`, `.saml.acs`, `.oauth.callback`, `.jwt.callback`; the SSO settings page gains an `endpoints` prop; shared `features.sso`.

### Security
- The old SAML check verified the signature over `SignedInfo` without checking the reference digests, so the signed content was not bound to the assertion, and it checked no Audience, Destination, InResponseTo or replay. It is replaced by `onelogin/php-saml` as above.

### Changed
- TOTP uses `pragmarx/google2fa` (new dependency) instead of hand-written code, and new secrets are 32 base32 characters (160 bits) instead of 16. Existing secrets keep working.
- `PUT {prefix}/admin/users/{user}/roles` (`escalated.admin.users.roles`, needs `role.manage`) replaces a user's roles; you cannot remove your own `role.manage`. While roles are on, the users page also receives each user's `roles` and the assignable `roles`.

## [1.9.11] - 2026-10-28

### Upgrading
- **Disabled guest, widget and chat routes answer 404.** These features are switched by admin settings stored in the database, so their routes stay registered (route caching cannot read settings) and now answer 404 while the feature is off, through the new `EnsureFeatureEnabled` middleware:
  - `guest_tickets_enabled` (default on): every `{prefix}/guest/*` route, including viewing, replying to and rating an existing guest ticket by its link, and widget ticket creation, verification and lookup for tickets. Before, viewing, replying and rating kept working with guest tickets off, and widget ticket creation, verification and lookup answered 403.
  - `widget_enabled` (default off): widget `/config`, `/articles`, `/articles/{slug}`, `/tickets` and `/tickets/{reference}`, which answered 403 before. The bundled widget already treats a failed `/config` as "no settings".
  - `chat_enabled` (default off): the visitor chat routes (`/chat/start`, `/chat/{session}/message(s)`, `/typing`, `/end`, `/rate`) and chat verification. Before, start and verification answered 403 and the others still served an existing chat. `/chat/availability` still answers `{"available": false, "message": "Chat is disabled."}` so the widget can hide its chat tab. The agent chat screens and chat settings stay available so admins can turn chat on and agents can wrap up.
  Hosts that expected 403 from these routes should treat 404 the same way. Nothing changes while the features are on, and guest tickets stay on by default.
- **The cloud webhook receiver is registered only when `escalated.hosted.signing_secret` (`ESCALATED_CLOUD_SIGNING_SECRET`) is set.** Without it, `POST /escalated/cloud/webhook` now answers 404 instead of 503. Synced installs that already set the secret are unaffected; if you cache routes, run `php artisan route:cache` again after setting it.

### Security
- **`escalated:tenant-retry` again refuses a failed job whose tenant does not own its models.** Laravel 13's `queue:retry` now catches `ModelNotFoundException` while it reads the job's retry deadline and re-queues the job anyway, so a payload tagged with one tenant but carrying another tenant's models went back on the queue instead of staying failed. The command now restores the job's models under the job's own tenant before re-queuing it; a mismatch fails that ID and leaves it in the failed-jobs table, as before.

## [1.9.10] - 2026-10-22

### Upgrading
- Import adapters should send each attachment's bytes as `content` (a string of the file's bytes); the importer stores them on the attachment disk like any upload, subject to `escalated.tickets.max_attachment_size_kb`. An attachment record that sends only a `path` is now accepted only when that path is a plain relative path inside `escalated.attachments.import_path` (default `escalated/imports`, env `ESCALATED_IMPORT_ATTACHMENTS_PATH`) on the import disk and the file exists; any other path fails that record and is logged on the import job. Adapters that wrote files elsewhere on the disk should move them under that directory or send `content`.

### Security
- **Import adapters can no longer point attachments at arbitrary files.** The importer saved whatever `path` an adapter returned as the attachment's path on the import disk, so a misbehaving or compromised adapter could make an imported attachment serve any file on that disk (the host's own files, or another ticket's private attachment) to whoever can download it. Paths are now confined to the import directory: absolute and drive paths, stream wrappers, backslashes, NUL bytes, `.`/`..` segments and look-alike prefixes are rejected.

### Fixed
- Imported tickets failed to save: the importer wrote the adapter's `title` to a `title` column the tickets table does not have. It now writes `subject` (from `subject`, else `title`) and `description`.
- Imported attachments failed to save because `original_filename` was never set. It now records the adapter's `filename`.

## [1.9.9] - 2026-10-22

### Fixed
- **The agent detail report shows the agent.** `GET admin/reports/agent/{id}/detail` (`Escalated/Admin/Reports/AgentDetail`) sent `agent_id`, `metrics` and `percentiles`, none of which the shared screen reads, so it rendered "Unknown Agent" and zeroes. It now sends what the screen declares: `period_days`, `agent {id, name, email}`, `total_tickets`, `resolved_tickets`, `avg_frt`, `avg_resolution`, `csat_score` (null without ratings), `response_percentiles {p50..p99}`, `productivity` (per day, `{label, value, values: [replies, resolved]}`), `workload` (tickets created per day), `csat_breakdown` (`N stars` counts), and `prev_*` for the same number of days before. Nothing else read the old keys. New `ReportingService::agentDetail()` computes them.

## [1.9.8] - 2026-10-22

### Upgrading
- Run `php artisan migrate`. The new migration adds a nullable `business_schedule_id` (foreign key to `business_schedules`, `ON DELETE SET NULL`) to `sla_policies` and `departments`, honouring the table prefix and `escalated.connection`. Until an admin creates a business schedule, business-hours deadlines are counted exactly as before, against `escalated.sla.business_hours`.
- Saving a webhook with the secret field left blank now keeps the stored secret, because the form no longer receives it. Send `clear_secret: true` to remove it.
- The SSO settings form posts the mask (`********`) back for a secret the admin did not change, and it is ignored; any other value replaces the stored secret, as before. The general settings page's older `abc*****` mask is still recognised.

### Added
- **Business schedules count SLA deadlines.** For a `business_hours_only` policy, `SlaService` counts in the policy's business schedule, else the ticket department's, else the default schedule, and only falls back to `escalated.sla.business_hours` when none applies. This applies when a policy is attached and when a department change selects it again. `BusinessHoursCalculator` now honours the schedule's time zone and holidays (one-off and recurring), counts real elapsed time across daylight-saving changes, and supports overnight windows and `24:00`; a schedule with no open day counts calendar time. See `docs/sla-policies.md#business-hours`.
- SLA policy and department saves accept `business_schedule_id` (nullable, must exist; tenant-checked). It changes only when sent, so forms that do not send it leave it as it is. Deleting a business schedule clears it from policies and departments. The SLA policy and department form pages receive a `schedules` prop (`[{id, name}]`, by name) for the shared frontend's **Business hours schedule** picker.

### Security
- **Stored secrets are no longer sent to admin pages.** The import pages received each import job with its decrypted source-platform credentials (`ImportJob` did not hide `credentials`), and the webhook index, edit and delivery-log pages received each webhook's signing secret. `ImportJob` now hides `credentials` and the import index and progress pages are built from an explicit field list (`ImportJob::toPageProps()`); the import run still reads the credentials server-side. `Webhook` hides `secret` and exposes `has_secret` instead. The SSO settings page now shows `sso_jwt_secret` and `sso_oauth_client_secret` as a mask instead of their values, and the general settings page masks the Mailgun signing key, Postmark inbound token and IMAP password completely (it showed their first three characters). `TwoFactor` (`secret`, `recovery_codes`) and `ApiToken` (`token` hash) hide their secrets too, though no page sent them.

### Fixed
- A business-hours deadline counted against `escalated.sla.business_hours` whose `timezone` differs from the app time zone was stored as that zone's wall-clock time, so it was off by the difference between the two zones. It is now stored at the right instant. Installs whose configured zone matches the app zone (the defaults are both UTC) are unaffected.

## [1.9.7] - 2026-10-21

### Upgrading
- Run `php artisan migrate`. The new migration rewrites stored workflow `trigger_event` values from legacy names (`ticket.replied`, `internal_note.added`, `tag.added`, `chat.session_started`, ...) to their canonical names (ADR 2026-10-21-event-vocabulary). Workflows saved under a legacy name keep firing without it.
- The endpoint in `escalated.notifications.webhook_url` receives every event, so it now also receives `chat.started`, `chat.assigned`, `chat.transferred`, `chat.message` and `chat.ended`. Admin webhooks receive them only once subscribed.
- The workflow builder offers `reply.created` instead of `ticket.replied`.
- Required department is opt-in (`escalated.tickets.require_department`, default `false`); existing installs are unaffected.

### Added
- **Required department.** New `escalated.tickets.require_department` (default `false`, env `ESCALATED_REQUIRE_DEPARTMENT`) and `escalated.tickets.default_department_id` (env `ESCALATED_DEFAULT_DEPARTMENT_ID`). When required, a ticket created without a department gets the default; with no default (or a default that no longer exists) creation fails with a `department_id` validation error: a 422 on the API, widget, mobile and guest endpoints, a redirect back with errors on web forms, and a `failed` inbound email. Enforced centrally when the `Ticket` model is created; imports are exempt. Existing installs are unaffected. See `docs/ticket-access-and-routing.md`.
- **Live chat webhooks.** Outbound webhooks now receive `chat.started`, `chat.assigned`, `chat.transferred`, `chat.message` and `chat.ended`, and the webhook form offers them (workflows already ran on them). The payload is `{"chat": {"session_id", "ticket_id", "status", "agent_id", "department_id"}}`, plus `from_agent_id`/`to_agent_id`/`to_department_id` for a transfer, `reply_id`/`author_type` (`agent` or `visitor`) for a message and `ended_by` for an end. It never carries the visitor's session token (or its hash), the visitor's email or the message body. Upgrade note: admin webhooks receive them only once subscribed, but the endpoint in `escalated.notifications.webhook_url` receives every event, so it now gets these five as well. A subscription stored as `chat.session_started` / `chat.session_ended` receives `chat.started` / `chat.ended`.
- **More workflow triggers.** Workflows can now run on `note.created`, `ticket.unassigned`, `ticket.priority_changed`, `ticket.department_changed`, `ticket.tag_added`, `ticket.tag_removed`, `chat.assigned`, `chat.transferred` and `chat.message`, and the builder offers them. A note or reply written by a workflow does not trigger `note.created` / `reply.created` workflows.

### Changed
- **Event vocabulary (ADR 2026-10-21-event-vocabulary).** The reply workflow trigger is now `reply.created`, the name webhooks already use. The builder offers `reply.created` instead of `ticket.replied`, and workflows fire under it. Upgrade note: run `php artisan migrate`; the new migration rewrites stored workflow `trigger_event` values from legacy names (`ticket.replied`, `internal_note.added`, `internal_note.created`, `tag.added`, `ticket.tagged`, `tag.removed`, `ticket.untagged`, `chat.session_started`, `chat.session_ended`) to their canonical names. Workflows saved under a legacy name keep firing without the migration, and a legacy name posted to the workflow form is stored canonically. `WorkflowLog.trigger_event` records the canonical name from now on.
- Webhook subscriptions saved under any of those legacy names now receive the canonical event (previously only `internal_note.added`, `tag.added` and `tag.removed` were mapped). `Webhook::LEGACY_EVENT_NAMES` now equals the new `Support\EventVocabulary::LEGACY`.

### Fixed
- Test suite: `tests/Feature/Api/SecurityTest.php` no longer fails intermittently. Its tickets took a random `ESC-nnnnn` reference that could collide with another random pick or with a reference generated from a primary key; they now get the model-generated reference, and test emails use a counter instead of `uniqid()`. No runtime change.

## [1.9.6] - 2026-10-20

### Added
- **Escalation rules and automations fire once per ticket.** A new `escalated_rule_executions` table (prefix and `escalated.connection` aware, tenant-scoped) records when a rule or automation acted on a ticket. The sweep skips a ticket the rule has already acted on, and re-arms the rule once the ticket stops matching. An optional `repeat_after_hours` column on `escalation_rules` and `automations` (null by default, meaning once) lets a rule fire again after that many hours while the ticket still matches. Run `php artisan migrate`.

### Changed
- The bundled widget is now `@escalated-dev/escalated` 0.12.3. It keeps its script settings when `embed.js` is placed in the page `<head>`, and Enter no longer picks a stale mention suggestion.
- Workflow, automation and escalation-rule actions that change status, priority, assignee or department now go through the same domain methods as an agent (`changeStatus`, `changePriority`, `assign`, `changeDepartment`). Transitions are checked against `escalated.transitions`, `resolved_at`/`closed_at` are stamped (so `escalated:close-resolved` closes workflow-resolved tickets), activity is logged and the status/priority/assignment/department events, notifications and webhooks fire. A change to the current value is a no-op. A transition the config does not allow, an unknown value, or a missing user or department is logged and skipped; previously the column was written regardless.
- `TicketUpdated` (`ticket.updated` webhooks and workflows) is no longer dispatched for the SLA deadline write when a policy is attached at ticket creation.

### Fixed
- With `escalated.sla.targeted` (and `escalated.sla.enabled`) on, a ticket that changes department now gets the SLA policy the new department would have assigned at creation. A new `ReselectSlaPolicy` listener on `DepartmentChanged` calls `SlaService::reselectPolicy()`, which anchors the new deadlines on the time of the change, clears a deadline the new policy does not set, keeps a recorded first response's deadline and recorded breaches, and leaves resolved and closed tickets alone. Previously the ticket kept the policy it was created under. Hosts with targeted SLAs off are unaffected. `SlaService::selectPolicy()` exposes the creation-time selection.
- One ticket that could not be escalated (already escalated, `waiting_on_customer`, an invalid priority value) aborted `escalated:evaluate-escalations` for every later rule and ticket. Each ticket and action is now isolated: failures are logged and the sweep continues; an already-escalated ticket is skipped.
- Escalation rules and automations re-ran on every sweep, re-adding notes, re-assigning and re-dispatching events every few minutes (see Added).
- A workflow on `ticket.updated` that wrote the ticket (e.g. `snooze_ticket`) re-triggered itself without end. `WorkflowEngine::processEvent()` now has a re-entrancy guard: the same event does not re-run workflows for the same ticket while they are running, and nesting stops at `WorkflowEngine::MAX_DEPTH` (3).
- `TicketEscalated` was dispatched twice per rule escalation, and a manual escalation put the agent's serialized user record into the event's `reason` (broadcast payload and notification). It is now dispatched once; the reason is the rule name for rule escalations and null for manual ones.
- Live chat: the visitor's opening message is now stored as the first chat reply, so it appears in the widget transcript and the agent's chat (it remains the ticket description; no extra `ReplyCreated` is dispatched).
- `GET widget/chat/availability` returns `message` (the key the widget reads) alongside `reason` when chat is disabled.
- Admin Settings passes `supported_settings` listing exactly the keys it saves, so the shared page no longer shows live-chat and widget-appearance controls that were silently discarded.

## [1.9.5] - 2026-10-19

### Changed
- The bundled widget is now `@escalated-dev/escalated` 0.12.2. It hides its Check Status tab when `/config` returns `status_lookup_enabled: false`.

## [1.9.4] - 2026-10-19

### Added
- **Embed the widget, ticket form and live chat on other sites.** List the embedding sites in `escalated.widget.allowed_origins` (`ESCALATED_WIDGET_ALLOWED_ORIGINS`) or the new **Sites that can embed the widget** admin setting (`widget_allowed_origins`); `https://*.example.com` subdomain wildcards are supported. Widget requests from a listed origin get CORS headers (including on 422 and 429 answers), skip the CSRF check and always run as a guest. Requests from any other origin are handled exactly as before. Empty by default, so nothing changes until a site is listed. See `docs/embedding-the-widget.md`.
- `GET {prefix}/widget/embed.js` serves the standalone widget bundle built from `@escalated-dev/escalated`, so other sites need one script tag. `escalated.widget.serve_script` turns the route off.
- Widget tickets and live chats keep host parameters sent as `metadata` (order number, plan, page URL, ...), sanitised to a flat map of short scalar values, plus a server-recorded `embedded_on` origin. `/config` advertises `accepts_metadata: true`.

### Fixed
- The admin settings page now saves the Support Widget settings it already showed (`widget_enabled`, colour, position, greeting, departments). The controller dropped them, so the widget could not be switched on from the UI.
- Live chat `metadata` from the widget was stored unbounded; it is now sanitised like ticket metadata.

## [1.9.3] - 2026-10-14

### Fixed
- Changing a ticket's department no longer throws a `TypeError`. `Ticket::changeDepartment()` dispatched `DepartmentChanged` with the `Department` model where the event takes the department id, so the agent and admin ticket screens, the API macro endpoint, bulk and macro actions, and escalation rules' `change_department` action all failed after the column had been written. The event's signature is unchanged.
- `TicketUnassigned` accepts a string (UUID/ULID) previous agent id, matching `TicketAssigned`. Unassigning a ticket on a host with string user keys threw a `TypeError`. `Ticket::assign()` reports the assignee's model key rather than reading `$user->id`.

## [1.9.2] - 2026-10-14

### Performance
- Read every setting with one query per request instead of one query per key. The shared Inertia props alone read five settings on every page. Settings are memoized per connection and tenant, flushed on writes and at the start of each request, queued job and Octane operation.

## [1.9.1] - 2026-10-13

### Fixed
- Preserve exact account identities, including trailing spaces, across MySQL visibility scopes, account filters and routing conditions.
- Verify department restrictions through customer mobile API tokens used by staff.

### Added
- Opt-in department visibility for agents across ticket queries, policies, API, widget access, bulk actions, reports, notifications, and broadcast authorization. Administrators retain access within their tenant.
- Nullable polymorphic ticket accounts with host resolution hooks, account filters, and independent account visibility for agents.
- Account and ticket-type conditions for routing rules, plus opt-in SLA targets by department and type with deterministic fallback.
- Integration documentation and regression coverage for default behavior, tenant boundaries, separate connections, and custom table prefixes. Run the additive migrations before enabling the new options.

## [1.9.0] - 2026-10-08

This release contains breaking and behaviour changes. Read **Upgrading** below
before deploying.

### Upgrading
- **Ship the shared frontend first.** This release needs
  `@escalated-dev/escalated` ^0.12.0 (the package's `package.json` now asks for
  it). Its verified guest forms and account-scoped channels must be in the host's
  built assets before this backend is deployed, or guest ticket creation and
  tenant broadcasts stop working.
- **Run the migrations.** New migrations add the attachment migration journal,
  tenant namespaces on package tables, verified guest access and the Slack inbox:
  `php artisan migrate`.
- **Laravel 11 hosts need 11.39 or later** (`illuminate/contracts`
  `^11.39|^12.0|^13.0`). Laravel 12 and 13 are unchanged.
- **Attachments are private by default.** The default disk changes from
  `public` to `local` (`ESCALATED_ATTACHMENTS_DISK`), and serialized URLs are
  expiring signed download links that check ticket access. Files uploaded before
  this release stay on the public disk and stay publicly readable until moved:
  run `php artisan escalated:attachments:privatize --from=public --to=local`
  (dry run), then again with `--apply`, then remove any public/CDN copies. See
  `docs/private-attachments.md`. Hosts that disable all package routes must
  register the named `escalated.attachments.download` route themselves.
- **Guest permanent tokens no longer work.** Guest ticket creation, live chat and
  tracking lookup require a one-time code sent to the recipient's mailbox, and
  access is through expiring grants. Existing ticket and chat links stop
  authorizing requests; recipients renew access by verifying their email with
  their ticket reference. Configure outbound mail, a shared cache with atomic
  locks (for example Redis) and the same `APP_KEY` on every worker. See
  `docs/guest-access.md`.
- **Inbound email replies are accepted only from the ticket's requester.** A
  matched email becomes a reply only when `From` is the requester's address, and
  the reply is posted as the requester; the author is never taken from `From`.
  Anything else, including an agent replying to a notification email, opens a
  new ticket instead (it no longer replies to or reopens the matched ticket).
  Agents should reply in the app. Once `ESCALATED_EMAIL_INBOUND_SECRET` is set,
  only the signed Reply-To address on outbound notifications links mail to a
  ticket; replies without it (for example a new email with the reference in the
  subject) open a new ticket.
- **Tenant mode needs staff seats.** `Contracts\TenantResolver` requires
  `isAgent(Model $user, string $tenantId)` and
  `isAdmin(Model $user, string $tenantId)`. `UnconfiguredTenantResolver` returns
  `false` for both, so with `ESCALATED_TENANCY_ENABLED=true` all agent and admin
  access is denied until the host resolver implements seats. Host code that
  checks staff access should call `Escalated\Laravel\Support\StaffAccess::isAgent()`
  / `isAdmin()` / `isStaff()` instead of the `escalated-agent` /
  `escalated-admin` gates directly. Tenancy stays off by default; to adopt it,
  follow `docs/tenancy.md` (backfill with writers paused, then enable).
- **Guest endpoints are rate-limited per client IP** (60 requests, 5
  submissions, 30 replies per minute, shared across browser, widget and mobile).
  Configure trusted proxies, or every guest shares the proxy's budget, and point
  `cache.limiter` at a shared store on multi-server hosts. Hosts that throttle
  upstream can set `ESCALATED_GUEST_RATE_LIMITS_ENABLED=false`.
- **Only the requester can rate a ticket** from the customer portal; other users
  get 403.
- **Requester-facing payloads are allow-listed.** The customer and guest ticket
  pages and the mobile ticket resource no longer include ticket metadata,
  `external_reference`, `chat_metadata` or agent emails/IDs. Host overrides of
  these pages that read those fields must source them elsewhere.
- **Plugin HTTP routes need `@escalated-dev/plugin-runtime` v0.2.0** (HTTP
  contract version 1). Older runtimes get 503 before a plugin handler runs. The
  default `ESCALATED_PLUGINS_RUNTIME_COMMAND` now points at the runtime's
  `build/bin/escalated-plugins.js`. Cookie, Authorization, CSRF and proxy headers
  are no longer forwarded to plugins.

### Added
- **Host account isolation (tenancy, opt-in).** A host-supplied
  `TenantResolver` scopes package records, policies, route bindings, API tokens,
  host identity discovery, reports, scheduled and queued work, broadcasts and
  attachments to the current account, including when host users live on another
  database. `escalated:tenant-backfill` and `escalated:tenant-provision` commands.
  See `docs/tenancy.md`. (#213)
- **Tenant-local staff seats** through `TenantResolver::isAgent/isAdmin` and
  `Support\StaffAccess`, used by the agent/admin middleware, ticket and admin
  policies, API token abilities, shared Inertia props, agent directories,
  workflow assignment, broadcast channels, private attachments and the Slack
  service actor. (#219)
- **Verified, expiring guest access** for guest tickets, chat and tracking
  lookup, with email codes (ten-minute expiry, five attempts) and per-client and
  per-mailbox delivery budgets (`guest_access.challenges_per_client_per_hour`,
  `guest_access.challenges_per_mailbox_per_hour`). See `docs/guest-access.md`.
  (#215, #220)
- **Agent API: create tickets for a requester** (named recipient or host user)
  with metadata, a host-assigned tracking reference and ordered
  shipment/account/order subjects, plus GET/PUT subject endpoints. See
  `docs/agent-ticket-api.md`. (#216)
- **Durable Slack inbound.** Authenticated Slack messages are stored in an
  encrypted inbox and processed by the scheduler into tickets or public thread
  replies, with bounded retries and dead-letter recovery. See
  `docs/slack-inbound.md`. (#218)
- `escalated.guest_rate_limits.enabled` (`ESCALATED_GUEST_RATE_LIMITS_ENABLED`)
  to switch the guest limiters off. (#226)

### Security
- Inbound email replies are accepted only from the ticket's requester, and only
  through the signed Reply-To address when `ESCALATED_EMAIL_INBOUND_SECRET` is
  set. A `From` naming a staff address no longer posts as that agent. (#220)
- Customer portal ratings are limited to the ticket's requester. (#220)
- The guest, customer and mobile ticket payloads are serialized from allow-lists,
  so ticket metadata and agent emails no longer reach requesters. (#220, #224)
- Guest ticket and widget entry points share per-IP request, submission and
  reply quotas; blocked requests get 429 with `Retry-After` before any record is
  created. (#214, #226)
- `TenantReferences` rejects case-variant spellings of identity, reference and
  morph type columns. (#219)

### Fixed
- Slack inbound text too large for a ticket description or reply body is no
  longer dead-lettered as `message_too_large`. The body keeps the leading text
  that fits, cut on a character boundary, with a truncation note, and the full
  text is stored as a private `slack-message.txt` attachment on the ticket or
  reply. Only a failure to store that attachment fails the receipt
  (`attachment_storage_failed`, retried then dead-lettered); retries never
  duplicate it. See `docs/slack-inbound.md`.
- Slack inbound acknowledges authenticated events it does not route (unmapped
  workspaces, channels and direct messages, whitespace-only text, and a second
  app's copy of a recorded message) with 200 `{"ignored": true}` instead of
  403/400/409. Text is no longer capped at 16,384 bytes. Slack entities are
  decoded once before escaping, and a deleted linked ticket fails as
  `ticket_missing` without retrying. See `docs/slack-inbound.md`.
- The plugin HTTP bridge no longer forwards cookie, authorization, XSRF/CSRF or
  proxy headers to plugins, matches SDK `:param` route segments and sends their
  values, and encodes empty `params`/`query` as JSON objects.
- Attachments now use private storage by default and expiring application download
  URLs with ticket authorization, internal-note restrictions and customer token
  limits. A dry-run-first command copies, verifies and removes existing public
  files. See `docs/private-attachments.md` for required upgrade steps.
- Agent performance, CSAT, workload, productivity and requester search now
  resolve host identities on their own database connection. Reports and CSV
  exports no longer join the host users table from Escalated's database.
  Identity lookups honor morph aliases and the configured display column;
  same-named agents retain separate workload series. CI runs the complete
  suite with independent host and package databases to guard this boundary.
- Existence and uniqueness validation now follows the owning model's connection
  for ticket creation, assignment, bulk actions, skills, roles, knowledge-base
  categories, custom fields and mobile identities.
- Agent rankings and CSV exports no longer fail when an assigned agent has no
  first response or resolution yet. (#212)
- The customer and guest ticket pages load the satisfaction rating, so an
  already-rated ticket no longer shows the rating form. (#220, #224)

### Changed
- CI now exercises Laravel 13 on PHP 8.3 and 8.5, plus pinned Laravel 13.8.0
  compatibility. Pest 4 is allowed for the Laravel 13 test toolchain; Laravel
  11/12 retain their Pest 3 compatibility legs. Documentation distinguishes the
  Laravel 13 PHP minimum from the package's Laravel 11/12 minimum.
- The minimum Laravel 11 version is 11.39. (#213)
- The shared frontend dependency is `@escalated-dev/escalated` ^0.12.0, and the
  page and route-name fixtures are refreshed to 0.12.0.

## [1.8.6] - 2026-09-27

### Fixed
- **The workflow Logs page printed each run's actions as raw data.** The shared
  Logs page reads `actions_executed` as a count, as the NestJS reference sends
  it; this package passed the stored array through. `WorkflowController::logs`
  now sends the count, with the list still in `action_details`. (#206)

### Changed
- **The shared frontend is now `@escalated-dev/escalated` ^0.11.9**, whose admin
  and agent panels work on narrow screens: below 1024px the admin sidebar folds
  into a drawer behind a menu button, the agent nav folds into a menu, and
  tables scroll inside their cards instead of widening the page. It also adds
  optional panel theme hooks (`activeText`, `headerBg`, `headerText`,
  `logoTileBg`, `logoTileFg`), set in the host's `EscalatedPlugin` options. No
  backend change is needed; the page-name fixture is refreshed to 0.11.9.

## [1.8.5] - 2026-09-26

### Fixed
- **The Reports dashboard and the Workflows index failed to render.** The shared
  frontend builds links with Ziggy's `route()`, which throws on a name it does not
  know or a missing required parameter, and the throw takes the whole screen down.
  The Reports dashboard linked to `escalated.admin.reports.response-times` and
  `escalated.admin.reports.resolution-times`; this package registered those
  screens as `reports.frt` and `reports.resolution`. Both names now exist, at
  `/reports/response-times` and `/reports/resolution-times`, and the old names and
  URLs keep working.

  The Workflows index linked to `escalated.admin.workflows.logs` with no workflow,
  and the route required one. `GET /workflows/logs` now serves the shared Logs page
  across every workflow, in the shape it reads: `logs` as a list of runs (newest
  100) and `workflows` as `{id, name}` for its filter. It used to be sent a
  paginator and a single workflow, so the page listed nothing useful even when it
  was reached. `?workflow=<id>` narrows it to one workflow, and the per-workflow
  `/workflows/{workflow}/logs` URL of earlier releases redirects there. That route
  is now named `escalated.admin.workflows.workflow-logs`.
- **The advanced report charts drew nothing and the SLA screen threw.** Every chart
  on the report screens reads a list of `{label, value}`; these screens were sent
  ReportingService rows as they are (`bucket`/`count`, `date`/`avg_hours`,
  `period`/`total_breaches`, `group`/`avg`), so each bar was NaN high and the browser
  logged an SVG error per bar. The SLA screen's `at_risk_tickets` was a keyed count
  forecast where the screen filters a list of tickets, which threw and took the screen
  down once it rendered. The agent tables read `agent_id`/`agent_name`, the ranking
  reads `volume`, `avg_frt`, `avg_resolution` and `csat`, and the cohort tabs read
  `name`, `volume`, `avg_resolution`, `breach_rate` and `csat`; none of those were
  sent, and the priority tab was a volume time series. `Support\ReportScreens` now maps
  each screen's data to what it reads, as the Adonis and Django ports already do, and
  the SLA screen lists the open tickets whose next deadline falls in the next 8 hours.
  ReportingService keeps its shapes for the export and API, and gains
  `avg_resolution_hours` on `agentPerformanceRanking()` and
  `ticketCohortsByPriority()`. `tests/Feature/Admin/ReportScreenShapesTest.php` checks
  the shapes with data present.
- **The report period selector did nothing on the advanced reports.** The frontend
  sends the period as `days`; response times, resolution times, SLA trends, agent
  ranking, agent detail, cohorts, comparison and export read only `period`, so choosing 7 days reloaded
  the 30-day report. They now read `days` too, and `period` still wins.

### Added
- **`tests/Feature/RouteNameParityTest.php`**, asserting every route name the
  frontend passes to `route()` is registered, against the list vendored at
  `tests/Fixtures/escalated-route-names.json`. It is the route counterpart of
  `PageNameParityTest`: a controller test that asserts a 200 cannot see a link
  that throws in the browser.

## [1.8.4] - 2026-09-15

### Added
- **Synced mode delivers events through the queue.** `SyncedDriver` dispatches
  `SyncEventToCloud` (5 tries, exponential backoff, `ESCALATED_SYNC_QUEUE` to pick a
  queue) instead of calling the cloud inline with a 15-second timeout, and every event
  carries a stable `event_id` so cloud.escalated.dev ignores redeliveries. A cloud outage
  no longer slows or blocks ticket writes.
- **Cloud to site webhook receiver.** `POST /escalated/cloud/webhook` verifies
  `X-Escalated-Signature` with `ESCALATED_CLOUD_SIGNING_SECRET` and applies
  `ticket.updated` / `ticket.status_changed` from the cloud projection to the local
  ticket through `LocalDriver`, so agent actions taken in the cloud portal reach the
  site. Synced mode is now two-way. The route answers 503 until the secret is set.

### Changed
- The cloud vocabulary translation lives in `Escalated\Laravel\Support\CloudVocabulary`,
  shared by `CloudDriver` and the receiver.

## [1.8.3] - 2026-09-14

### Fixed
- **Cloud mode failed on every call.** `CloudDriver` handed the HTTP response object to
  `hydrateTicket(array)`, so `ESCALATED_MODE=cloud` threw a TypeError before any ticket was
  created, and `HostedApiClient` joined the base URL and endpoint without a slash
  (`/api/v1tickets/7`). The driver now decodes the JSON body, raises a `RequestException` on
  non-2xx responses, translates the cloud vocabulary both ways (`medium`/`critical` ↔
  `normal`/`urgent`, `waiting_on_*` ↔ `waiting`, `ticket_number` → `reference`), reads
  pagination from `meta`, and carries the host requester through `metadata.host_requester_*`
  so `requester_name` resolves to the local user. Cloud mode now has integration tests.

## [1.8.2] - 2026-09-13

### Security
- **Webhook URLs could point at internal addresses.** An admin could save a
  webhook for loopback, a private network or the cloud metadata address, and
  the delivery log showed whatever answered. URLs must now be http(s) and
  resolve only to public addresses. The check runs when a webhook is saved and
  again before every delivery. Delivery connects to the address that was
  checked and does not follow redirects. The workflow `send_webhook` action
  uses the same guard (#183).
- **A customer could subscribe to another customer's ticket channel.** Channel
  authorization compared user ids with `(int)` casts, which turns every ULID
  into `1` and every UUID into `0`, and ignored the requester's type. Ids are
  now compared as strings, and the requester's morph type must match (#185).

### Fixed
- **Webhooks created on the admin Webhooks page never received an event.**
  `DispatchWebhook` sent events only to `notifications.webhook_url`, and the
  dispatcher that fans out to the saved webhooks ran only when a delivery was
  retried by hand. Every event now goes to the active webhooks subscribed to it
  as well as to `webhook_url`. The form's `internal_note.added`, `tag.added` and
  `tag.removed` never matched the names events are sent under. They are now
  `note.created`, `ticket.tag_added` and `ticket.tag_removed`, and webhooks
  saved with the old names still receive them (#183).
- **A custom `table_prefix` broke a fresh install.** The newsletter migrations,
  models and validation rules named `escalated_*` tables directly, so any other
  prefix failed at the newsletter migrations. They now use the configured
  prefix (#184).
- **`scheduling.auto_register` did nothing.** The documented option was never
  read, so SLA checks, delayed workflow actions, snooze wake-ups and the rest
  never ran unless the host scheduled each command by hand. With it on, the
  package adds its recurring commands to the scheduler, and the optional ones
  only when their feature is enabled (#186).
- **Plugin `ctx.store.query` failed outside MySQL.** Filters and `orderBy` used
  `JSON_UNQUOTE(JSON_EXTRACT(...))`, which SQLite and PostgreSQL don't have, and
  MySQL sorted numbers as text. Queries are now written for each driver: SQLite,
  MySQL, MariaDB and PostgreSQL. Numbers compare as numbers, and field names are
  validated before they reach SQL (#187).
- **The widget ignored the knowledge base settings.** It reported the knowledge
  base as enabled and served articles when the admin had turned it off or made
  it private. It now follows both settings, like the customer knowledge base
  (#188).
- **The package read config keys the published config didn't define.**
  - `notifications.webhook_secret` was never set, so requests to `webhook_url`
    went unsigned. It is now `ESCALATED_WEBHOOK_SECRET`.
  - The ticket policy read `escalated.allow_customer_close` instead of
    `escalated.tickets.allow_customer_close`, so customers could never close a
    ticket.
  - `app_name`, `attachments.disk`, `authorization.agent_scope`, the plugin
    runtime options, `marketplace.*` and `version` are now defined, and a
    `null` value falls back to the default instead of being used as is (#189).

## [1.8.1] - 2026-09-13

### Fixed
- **Four report screens were blank.** `ReportController` rendered
  `Escalated/Admin/Reports/FirstResponseTime`, `ResolutionTime`,
  `CohortAnalysis` and `PeriodComparison`; the frontend package ships those
  components as `ResponseTimes`, `ResolutionTimes`, `Cohorts` and `Comparison`.
  Inertia resolves a page name with nothing behind it to nothing rather than to
  an error, so all four returned 200 and rendered an empty panel.

  The existing tests asserted only the status, which is why this survived. They
  now assert the component name as well.

- **The shared workflow builder could not save a workflow, and a saved one ran
  on every ticket.** The builder posts the body fixed in
  escalated-developer-context `domain-model/workflow-admin-contract.md`, and
  `WorkflowController` refused it: `set_department`, `add_note` and
  `insert_canned_reply` failed validation, omitting `conditions` was an error,
  and reorder required `ids`. Worse, `WorkflowEngine` read only the legacy
  `{match, rules}` shape, so a workflow stored as `{"all": [...]}` found no
  rules and matched every ticket it saw.

  Conditions are now read as `{all}`, `{any}`, a flat list or `{match, rules}`,
  and any other non-empty object matches nothing. The engine gains the
  `starts_with`, `ends_with`, `greater_or_equal` and `less_or_equal` operators,
  the `department_id` field, and the `set_department`, `add_note` and
  `insert_canned_reply` actions; `delay` also takes a plain number of minutes
  and `send_webhook` a plain URL. The Form page sends `workflow`,
  `trigger_events`, `action_types` and `operators`, and reorder accepts
  `workflow_ids`. A reply posted by a workflow no longer triggers workflows
  itself, so a canned reply on `ticket.replied` cannot answer itself forever.
  Stored `{match, rules}` workflows and the old action and operator names keep
  working.

### Added
- **`tests/Unit/PageNameParityTest.php`**, asserting every page name this
  package renders resolves to a component. It diffs them against the manifest
  the frontend publishes, vendored at `tests/Fixtures/escalated-pages.json`, and
  names the file each failing name came from. No single repo's tests can see a
  blank screen like the four above on their own: a controller test asserts a
  status, and the frontend never hears the name.

## [1.8.0] - 2026-09-12

### Changed
- **The test suite runs on MySQL and PostgreSQL as well as SQLite.** It had only
  ever seen SQLite, which is the one driver no host deploys on and the one that
  enforces the least. `tests/TestDatabase.php` reads `ESCALATED_TEST_DRIVER`,
  defaulting to SQLite so running the suite locally still needs nothing
  installed; an unrecognised value throws rather than falling back, because a CI
  leg that quietly ran SQLite would report green having tested nothing the
  matrix exists for. `tests/Integration/TestDatabaseDriverTest.php` is the one
  test that notices.

  The `tests/Connection` suite stays on two in-memory SQLite databases whatever
  the driver: what it exercises is Eloquent's connection resolution, which has
  no dialect content, and two empty schemas per test is the only way to tell
  connection routing from schema drift.

### Fixed
- **Every SLA report was a 500 on PostgreSQL.** Eight raw-SQL expressions in
  `ReportingService` compared the boolean columns `sla_first_response_breached`
  and `sla_resolution_breached` against the integer `1`. PostgreSQL rejects
  that outright ("operator does not exist: boolean = integer"); MySQL and SQLite
  store the columns as integers, so neither noticed. A boolean column is already
  a predicate and is now used as one, which every driver accepts.

- **An unknown user id could escape as a `QueryException` instead of the
  documented `InvalidArgumentException`.** `Ticket::assign()`, mention
  resolution, chat assignment and the assignment notification all passed
  whatever id they were given straight to `find()`. An id that cannot be the
  host user's key — a UUID against an integer key, say, or anything else from a
  request — made PostgreSQL and MySQL raise a driver error where SQLite quietly
  returned nothing. A host that handled "not found" would instead surface a 500
  with SQL in it. Lookups now go through `Escalated::findUser()`, which treats an
  impossible id as not found.

- **Three migrations could not be rolled back.**
  - `2026_03_21_000001` passed a complete index name inside the array form of
    `dropIndex()`, which asks Laravel to build a name from it — producing
    `escalated_tickets_escalated_tickets_ticket_type_index`, which no database
    has.
  - `2024_01_01_000011` restored `NOT NULL` on `requester_type` and
    `requester_id` while guest tickets — rows with neither — were still present.
    PostgreSQL and MySQL refuse; SQLite rebuilds the table and lets the nulls
    through. The rows a pre-guest schema cannot hold are now removed first.
  - The same migration dropped `guest_token` while its unique index still named
    it, which SQLite rejects. The index is dropped first.

## [1.7.0] - 2026-09-11

### Added
- **Admin screen for the database connection** at `/admin/settings/database`. Shows which database Escalated is reading and writing (driver, database, ticket count), lists the connections it could use, lets each be probed without committing to it, and switches between them.

  A connection with no Escalated tables cannot be selected. Pointing Escalated at an unmigrated database does not error — the panel comes up with no tickets, no departments and no settings, which reads exactly like data loss — so the server refuses it and the UI disables it with the reason shown.

  `escalated.connection` still wins. Set in config or `.env` it is deployed infrastructure, and the screen is read-only rather than accepting a change the backend would ignore. Precedence: `Escalated::useConnection()` → config → the admin's stored choice.

  The stored choice is a file (`storage/app/escalated/connection.php`), not a settings row. It cannot be a row: the name of the connection cannot live in the database it selects, and an admin pointing Escalated at an empty database would otherwise destroy the only record of how to point it back.

- **`Escalated::useConnection()`** — force a connection for the rest of the process (a queued job, a console command, a test) and restore the previous value. `Escalated::connectionIsPinnedByConfig()` reports whether config has the final say.

## [1.6.0] - 2026-09-11

### Added
- **Configurable database connection.** `escalated.connection` (env `ESCALATED_DB_CONNECTION`) names the connection Escalated's own tables live on. `null` keeps the host application's default connection, which is the historical behaviour and leaves an unconfigured host byte-identical. Every model, migration, query-builder read and transaction in the package follows it.

  Your users table is deliberately not moved: it belongs to the host, and Escalated follows the user model to wherever it already lives. Host user ids were already stored as plain unconstrained columns, so no foreign key has to span the boundary.

  Relations between an Escalated pivot table and the host's users table (department agents, role members, skill agents, ticket followers) cannot be a SQL join once the two are on different connections. They are resolved in two steps when that happens — read the pivot, then load the users by key — so `->agents`, `->followers`, `withCount('agents')`, `attach()`, `sync()` and `detach()` behave the same either way. On a single connection the ordinary join is still issued and nothing changes.

  Setting this on an existing install does not move existing data; migrate the tables first.

## [1.5.1] - 2026-06-04

### Fixed
- CI/build: ignore the `laravel/framework` 11.x security advisories that Composer 2.9+ now excludes during dependency resolution (all released 11.x are affected with no advisory-clean version yet), which was breaking the Laravel 11 compatibility test matrix. Added to this package's root `config.audit.ignore` only — it does **not** propagate to host applications, which govern their own audit policy. No runtime/code changes.

## [1.5.0] - 2026-06-04

### Added
- **Newsletter system** — admin-only broadcast feature: campaigns, recipient lists (static + dynamic segments), reusable templates, and per-recipient deliveries with open/click tracking, one-click unsubscribe, view-in-browser, and ESP bounce/complaint webhooks (Postmark/Mailgun/SES/SendGrid). Sending is driven by the `escalated:newsletters:dispatch` scheduled command, with per-minute rate limiting, retry backoff, and auto-pause on high bounce rates. Disabled by default behind `escalated.enable_newsletters`. The `created_by`/`sent_by`/`added_by` columns are stored UUID-safe so integer/UUID/string-keyed host users all work. (#103, #128)
- **`add_follower` workflow action** — auto-subscribe a host user as a ticket follower from a workflow rule. (#127)
- **Ticket subjects**: attach host-app entities (Project, Customer, asset, …) that a ticket is *about*, distinct from the requester. Models implement the new `Escalated\Laravel\Contracts\TicketSubject` contract (or use the `PresentsAsTicketSubject` trait) to expose a title/subtitle/url/color/icon for the ticket UI. A ticket can reference several subjects via `attachSubject()`/`detachSubject()`/`syncSubjects()`; they're serialized on `TicketResource` as a `subjects[]` array. Agent attach/detach endpoints resolve types strictly against the new `escalated.ticket_subjects.types` allowlist. `subject_id` is stored as a string so integer/UUID/string-keyed host models all work. (#89)
- The current user's permission slugs are now shared with the Inertia frontend as `escalated.permissions` (alongside `is_admin`/`features`), so the UI can gate navigation per-permission. (#129)

### Security
- Newsletter admin routes now **enforce** the seeded permissions: every admin action requires `newsletters.manage`, and send-class actions (saving/updating with a `scheduled`/`sending` status, and test-send) additionally require `newsletters.send`. Previously the permissions were seeded but never checked, so any admin-role user could perform every newsletter action. (#129)

## [1.4.1] - 2026-05-29

### Fixed
- Upgrade safety: add a dedicated migration (`add_routing_columns_to_escalated_skills_table`) that backfills the `routing_tag_ids`/`routing_department_ids` columns. v1.4.0 added them by editing the original `create_escalated_skills_table` migration, which never re-runs on an existing install — so apps upgraded from an earlier version were missing the columns and **every skill create/edit failed** (`Skill::saving()` always writes them). The new migration is guarded by `Schema::hasColumn`, so it is a no-op on fresh installs.
- `AssignTicketRequest` (Admin/Agent assign endpoints) again validates that `agent_id` exists (against the host user key), so an unknown/garbage id returns a clean `422` instead of a `500`. The `integer` rule remains dropped so UUID/string keys are accepted (matching `Api\TicketController`).
- `AssignmentService::__construct()` `$skillRoutingService` is now optional (resolved from the container when omitted), restoring the v1.3.0 `new AssignmentService($manager)` single-argument signature for direct instantiation / subclasses.

### Notes (upgrading from < 1.4.0)
- **`int` → `int|string` widening (UUID/string user-key support).** The `TicketDriver` contract's `assignTicket()` and several public `Ticket` methods (`assign`, `follow`, `unfollow`, `isFollowedBy`, `scopeAssignedTo`) now accept `int|string`. If your app **implements `TicketDriver`** or **subclasses `Ticket`** and type-hinted these parameters as `int`, widen them to `int|string` to avoid a PHP "must be compatible" fatal. Apps that only *call* these methods are unaffected.
- The `TicketAssigned` event's `$agentId` is now `int|string`; for UUID/string-keyed apps the broadcast `agent_id` is a string. Integer-keyed apps are unchanged.

## [1.4.0] - 2026-05-29

### Added
- Custom Ticket Actions: host apps can register agent ticket buttons that dispatch a `TicketCustomActionTriggered` event (with an audit internal note) when clicked, exposed to the agent UI as `customActions` and to the API as `custom_actions`. (#107, #108)
- Auto-detect the host user key type for the package's user-referencing migration columns. `Escalated::userForeignColumn()`/`userMorphs()` now type `user_id`/`assigned_to`/`requester`/`author`/`causer`/pivot columns to match the configured `user_model` — `unsignedBigInteger` for integer keys, string-compatible columns for `HasUuids`/`HasUlids`/string keys — so UUID/string-keyed apps migrate cleanly with no manual edits. Override via the new `escalated.user_key_type` config (`auto` by default). (#112)
- Skills-based ticket routing: assign tickets to agents by matching required skills. (#95)
- Mobile customer and guest support API endpoints. (#104)
- Expanded SSO provider configuration surface. (#96)
- Consume translation strings from the shared `escalated-dev/locale` Composer package so wording stays consistent across every Escalated host plugin. The `EscalatedServiceProvider` now stitches three layers under the `escalated` namespace: the central package (canonical), `lang/vendor/escalated/` in this repository (Laravel-specific overrides), and the host app's `lang/vendor/escalated/` (consumer overrides via `php artisan vendor:publish --tag=escalated-lang`). The package's own `resources/lang/` is retained as a fallback for environments where the central package has not yet been composer-installed.

### Fixed
- Support UUID/string host-app user keys throughout. `SavedView::scopeForUser()` (and every other user-id parameter) now accepts `int|string` instead of hard-typing `int`, fixing a `TypeError` 500 (`Argument #2 ($userId) must be of type int, string given`) that hit apps with non-integer user keys when opening `/support/admin/tickets`. Incoming user ids are no longer cast to `int` anywhere (which corrupted UUIDs). (#110)
- Restrict agent skill assignment to role-bearing users and wrap skill store/update in transactions. (#100)
- Show 2FA recovery codes after successful confirmation. (#97)

### Security
- Bump the transitive `qs` dependency in the demo host-app from 6.15.1 to 6.15.2 to remediate CVE-2026-8723 (NULL pointer dereference). (#113)

## [1.2.1] - 2026-04-18

### Fixed
- Emit Postgres-compatible SQL from `ReportingService` and `ReportController::avgFirstResponseHours`. Previous `TIMESTAMPDIFF(HOUR, ...)` and `DATE_FORMAT(...)` calls (MySQL-only) caused `/support/admin/reports` to 500 on Postgres deployments with `column "hour" does not exist`. Date/time and date-format helpers now branch on driver across `sqlite | pgsql | mysql`. (#60, fixes #59)

### Internal
- Added Docker dev/demo environment under `docker/` (excluded from the Composer dist via `archive.exclude`). `docker compose up --build` boots a Postgres-backed Laravel host with the package installed and a click-to-login picker. (#58)
- Production PSR-4 autoload now includes `Escalated\Laravel\Database\Factories\` so `Model::factory()` resolves at runtime in real installs. Same class of bug as #55. (#58)

## [1.2.0] - 2026-04-18

### Security
- Block SSRF in `WorkflowEngine::actionSendWebhook()` by validating URL scheme and rejecting URLs that resolve to private/reserved IPs (#49)
- Prevent regex injection (ReDoS) in `compareValues()` `matches` operator via `safeRegexMatch()` with pattern validation and a PCRE backtrack limit (#49)
- Enforce strict `in:` validation for `actions.*.type` in `WorkflowController` store/update to prevent arbitrary action type injection (#49)
- Whitelist allowed fields (`subject`, `description`, `ticket_type`, `channel`) in `resolveFieldValue()` default case instead of open `$ticket->{$field}` (#49)
- Apply granular rate limiting: ticket creation `5/min`, chat start `5/min`, chat message `30/min` (#49)
- Add `AuditLog` entries for workflow create/update/delete and report exports (#49)

### Fixed
- Register `Escalated\Laravel\Database\Seeders` namespace in production autoload so `php artisan escalated:install` can run the permission seeder when the package is installed as a dependency (#56)
- Include `url` in attachment serialization (#50)
- Include computed ticket fields in serialization (#51)
- Include chat, context panel, and activity fields in ticket serialization (#52)
- Move expensive computed fields from `$appends` to detail-only serialization to keep list endpoints fast (#53)
- Add missing workflow and workflow log computed fields (#54)

### Internal
- CI: switch `minimum-stability` to `stable` and add `audit.ignore` for two phpunit advisories that were blocking the resolver from selecting any compatible phpunit version (#57)

## [1.1.0] - 2026-04-06

### Fixed
- Dispatch TicketCreated event after reference generation, restore priority cast
- Set ticket status if not present
- Add Notifiable trait to HasTickets trait
- Move reference generation to model hook

## [1.0.0] - 2026-04-06

### Added
- Custom Fields & Forms
- Custom Statuses
- Business Hours & Schedules
- Custom Agent Roles / RBAC
- Audit Log system
- Ticket merging
- Problem/incident linking
- Side conversations
- Agent collision detection
- Light agents support
- Skills-based routing
- Agent capacity management
- Outbound webhooks
- Time-based automations
- Category column on escalation rules
- Knowledge base models, migration, and controllers
- Two-factor authentication backend
- SSO service, controller, and routes
- Data retention purge command and controller
- Email channel service and controller
- Conditions column on custom fields
- Custom objects backend (models, migration, controller)
- CSAT settings controller
- Reporting service and enhanced report controller
- Configurable user display column for agent select
- Show powered-by setting
- Import system with CLI command, admin controller, adapters, and resumability
- Plugin bridge for JSON-RPC communication with SDK-based plugins
- Artisan plugin marketplace command
- Granular permission seeder with default roles
- Expanded ticket search to requester with advanced filter params
- Ticket type categorization with automation support
- SAML validation, JWT validation, and DKIM status check
- Inertia v2 + v3 and Laravel 13 support
- Make Inertia UI optional with core-only boot mode

### Fixed
- Prevent false positive trait detection in addHasTicketsTrait
- Update Inertia render path for Plugins page to match other backends
- Missing use ($prefix) in knowledge base migration closure
- Consistently use configurable table prefix in migrations
- Register LogTicketStatusChange listener for TicketStatusChanged event
- Resolve bugs in model-functions PR
- Pass $prefix to Schema::create closure via use() keyword

### Security
- Fix 6 critical vulnerabilities

## [0.6.0] - 2026-02-18

### Added
- REST API layer with token auth, rate limiting, and full ticket CRUD
- Multi-language (i18n) support with EN, ES, FR, DE translations
- Auto-configure User model during escalated:install

### Fixed
- Enforce token abilities, Gate checks, and validation on API routes

### Security
- Add OWASP security tests and fix remaining vulnerabilities

### Changed
- Reorganize controllers and tests into feature-based subdirectories

## [0.5.0] - 2026-02-11

### Added
- WordPress-style plugin/extension system
- Composer plugin discovery

### Fixed
- Rewrite CI to use standard Laravel package testing pattern
- Resolve CI test failures by registering package autoload-dev paths
- Use idiomatic app/Plugins/Escalated path instead of resources/

## [0.4.0] - 2026-02-09

### Added
- Bulk actions for assigning, changing status/priority, adding tags, closing, or deleting multiple tickets
- Macros for reusable multi-step automations
- Ticket followers with shared notifications
- Satisfaction ratings (1-5 star CSAT with optional comments)
- Pinned internal notes

## [0.1.9] - 2026-02-08

### Security
- Fix critical SSRF, XSS, auth bypass and high-severity vulnerabilities

## [0.1.8] - 2026-02-08

### Added
- Inbound email system with Mailgun, Postmark, AWS SES, and IMAP adapters
- Admin settings override for all inbound email adapter credentials with config/env fallback

### Fixed
- Resolve all test failures for testbench compatibility

## [0.1.7] - 2026-02-08

### Added
- Admin ticket management and configurable reference prefix

## [0.1.6] - 2026-02-08

### Added
- EscalatedSettings model, admin settings, guest tickets

## [0.1.5] - 2026-02-08

### Fixed
- Resolve ticket 404s and enhance install command

## [0.1.4] - 2026-02-08

### Fixed
- Register event listeners individually instead of as arrays
- Prevent customer {ticket} wildcard from matching agent/admin paths

## [0.1.3] - 2026-02-08

### Added
- Restore Ticketable interface, add Inertia prop sharing

## [0.1.2] - 2026-02-08

### Fixed
- Replace Ticketable type hints with Model for better DX

## [0.1.1] - 2026-02-07

### Fixed
- Add date prefixes to migrations for correct dependency ordering

## [0.1.0] - 2026-02-07

### Added
- Initial release of Escalated Laravel package
- Ticket lifecycle management (create, assign, reply, resolve, close, reopen)
- SLA engine with per-priority targets and breach detection
- Escalation rules with condition-based automation
- Agent dashboard with filters, bulk actions, internal notes, canned responses
- Customer portal for self-service ticket management
- Admin panel for departments, SLA policies, escalation rules, tags, and reports
- File attachments with configurable storage
- Activity timeline and audit logging
- Email notifications with webhook support
- Department routing with round-robin auto-assignment
- Tagging system with colored tags
- Frontend moved to @escalated-dev/escalated npm package
