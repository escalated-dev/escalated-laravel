# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
