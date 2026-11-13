# Features

## Features

- **Ticket lifecycle** — Create, assign, reply, resolve, close, reopen with configurable status transitions
- **SLA engine** — Per-priority response and resolution targets, business hours calculation, automatic breach detection
- **Escalation rules** — Condition-based rules that auto-escalate, reprioritize, reassign, or notify
- **Agent dashboard** — Ticket queue with filters, bulk actions, internal notes, canned responses
- **Customer portal** — Self-service ticket creation, replies, and status tracking
- **Admin panel** — Manage departments, SLA policies, escalation rules, tags, and view reports
- **File attachments** — Drag-and-drop uploads with configurable storage and size limits
- **Activity timeline** — Full audit log of every action on every ticket
- **Email notifications** — Configurable per-event notifications with webhook support
- **Department routing** — Organize agents into departments with auto-assignment (round-robin)
- **Tagging system** — Categorize tickets with colored tags
- **Guest tickets** — Email-verified submission, expiring private links, and host-supplied tracking lookup; see [verified guest access and upgrade requirements](guest-access.md)
- **Admin inside your own admin panel (optional)** — Off by default; the standalone admin stays the default. Turn it on to mount Escalated's admin under your prefix and middleware, render it inside your Inertia layout or Blade template (or an allow-listed iframe), and list its menu in your own sidebar; see [embedding the admin](embedding-admin.md)
- **Embed on other sites** — Put the widget, ticket form or live chat on a store or marketing site with one script tag and host parameters; see [embedding the widget](embedding-the-widget.md)
- **Access control** — Roles & permissions, two-factor authentication and SSO (SAML, OAuth/OIDC, JWT) as install-level switches that is enforced on every staff route when on and hidden when off; see [access control](access-control.md)
- **Agent integration API** — Create tickets for host users or named contacts with metadata, tracking references and ordered host-model subjects in one call; see [the API contract](agent-ticket-api.md)
- **Inbound email** — Create and reply to tickets via email (Mailgun, Postmark, AWS SES, IMAP)
- **Inertia.js + Vue 3 UI** — Shared frontend via [`@escalated-dev/escalated`](https://github.com/escalated-dev/escalated)
- **Ticket splitting** — Split a reply into a new standalone ticket while preserving the original context
- **Ticket snooze** — Snooze tickets with presets (1h, 4h, tomorrow, next week); `escalated:wake-snoozed-tickets` Artisan command auto-wakes them on schedule
- **Saved views / custom queues** — Save, name, and share filter presets as reusable ticket views
- **Embeddable support widget** — Lightweight `<script>` widget served via `/support/widget/*` routes with KB search, ticket form, and status check
- **Email threading** — Outbound emails include proper `In-Reply-To` and `References` headers for correct threading in mail clients
- **Branded email templates** — Configurable logo, primary color, and footer text for all outbound emails
- **Real-time broadcasting** — Opt-in broadcasting via Pusher, Reverb, or Soketi with automatic polling fallback
- **Knowledge base toggle** — Enable or disable the public knowledge base from admin settings
- **CI: Laravel Pint** — Automated code style enforcement on every pull request
