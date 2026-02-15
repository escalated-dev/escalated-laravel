# Changelog

All notable changes to Escalated will be documented in this file.

## [0.5.0] - 2026-02-11

### Added
- Plugin storage moved to `app/Plugins/Escalated`
- Composer plugin auto-discovery via `vendor/*/*/plugin.json`
- Dual-source plugin loading (local + Composer) with `source` field
- Admin Plugins page with upload, activate/deactivate, and delete controls
- Plugins sidebar link with puzzle-piece icon
- Source badges (local/composer) on plugin list
- Composer plugin deletion guard (controller + service layer)
- Plugin authoring documentation (`docs/plugins.md`)
- PluginService unit tests (15 tests)
- AdminPluginController feature tests (8 tests)

## [0.1.0] - 2026-02-07

### Added
- Initial release of Escalated Laravel package
- Three hosting modes: self-hosted, synced, and cloud
- Full ticket lifecycle management (create, reply, assign, resolve, close, reopen)
- SLA policy engine with business hours support and breach detection
- Escalation rules engine with condition-based evaluation
- Customer portal with ticket creation and reply
- Agent dashboard with ticket queue and filters
- Admin panel with departments, SLA policies, escalation rules, tags, canned responses, and reports
- Internal notes for agent-only communication
- File attachment support with configurable storage
- Tag management system
- Department management with agent assignment
- Canned responses for quick replies
- Activity timeline logging
- Email notifications for all ticket events
- Webhook support for external integrations
- Artisan commands: check-sla, evaluate-escalations, close-resolved, purge-activities
- Inertia.js + Vue 3 UI components (publishable)
- Separate publish tags for client and admin assets
- Full test suite with unit, feature, and integration tests
