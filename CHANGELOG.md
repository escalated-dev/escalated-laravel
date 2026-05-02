# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Consume translation strings from the shared `escalated-dev/locale` Composer package so wording stays consistent across every Escalated host plugin. The `EscalatedServiceProvider` now stitches three layers under the `escalated` namespace: the central package (canonical), `lang/vendor/escalated/` in this repository (Laravel-specific overrides), and the host app's `lang/vendor/escalated/` (consumer overrides via `php artisan vendor:publish --tag=escalated-lang`). The package's own `resources/lang/` is retained as a fallback for environments where the central package has not yet been composer-installed.

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
