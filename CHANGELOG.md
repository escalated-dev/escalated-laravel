# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
