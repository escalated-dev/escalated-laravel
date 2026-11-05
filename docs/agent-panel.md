# Agent panel data

What this package sends the shared frontend's agent panel beyond the ticket
pages' required props. The contract is ADR
[2026-11-05-agent-panel-data](https://github.com/escalated-dev/escalated-developer-context/blob/main/decisions/2026-11-05-agent-panel-data.md)
in escalated-developer-context; the frontend side is its `docs/agent-panel.md`.
Everything here is optional: an older frontend ignores it.

## The agent's own status

Every agent, light agents included, sets their own Online / Away / Offline
status:

| Route | Name | Body | Answer |
|---|---|---|---|
| `PUT` or `POST /{prefix}/agent/status` | `escalated.agent.status` | `{ "status": "online" \| "away" \| "offline" }` | `{ "agent_status": "away" }` (JSON request); back (Inertia visit); 422 for any other value |

The status is stored in the agent profile's `chat_status`, the field live-chat
routing reads, so an Away or Offline agent gets no new chats. The admin
live-chat route `escalated.admin.chat.status` keeps working as an alias for
the same field and now also answers `agent_status`.

Agents (not customers) get two shared props in `escalated`:

- `agent_status`: their current status; `offline` until they set one (the
  column default, and what chat routing assumes);
- `agent_statuses`: `["online", "away", "offline"]`.

With roles & permissions on, the route needs no permission: it is personal.
No event is emitted; the event vocabulary has none for it.

## Knowledge-base search

`GET /{prefix}/agent/kb/search?q=...` (`escalated.agent.kb.search`) feeds the
ticket workspace's Related panel and the composer's "insert article link".

- `q`: 2 to 200 characters; `limit`: 1 to 20, default 10. Anything else is a 422.
- Answer: `{ "data": [{ "id", "title", "excerpt", "url", "category": { "id", "name" } | null, "updated_at" }] }`.
  `excerpt` is the body as plain text, cut at about 160 characters; `url` is
  the article on the customer knowledge base (`escalated.customer.kb.show`).
- Published articles only; this package has no agent-only articles, and
  drafts are never returned.
- Matching is a case-insensitive `LIKE` on title and body with `%` and `_` taken literally; title
  matches come first, then the most recently updated.
- 404 while the knowledge base is turned off. With roles & permissions on it
  needs `kb.view`.

The same escaping now applies to the customer knowledge base and widget
article search, where a `%` or `_` in the search used to match anything.

## Unread tickets

A ticket is unread for an agent when the customer side did something the
agent has not seen since they last opened it.

- **Customer activity** is the ticket's `last_customer_activity_at`: set when
  the ticket is created (not while importing) and on every public reply or
  chat message from the requester, a contact, a guest, or a host user who is
  not staff. Agent replies, internal notes, and replies written by workflows,
  automations or the system never move it. Tickets that existed before the
  upgrade have none, so they start out read.
- **Read markers** live in `escalated_ticket_reads` (`user_id`, `ticket_id`,
  `last_read_at`), one row per agent and ticket. `last_read_at` NULL means the
  agent marked the ticket unread.
- Opening a ticket in the agent panel (`escalated.agent.tickets.show`) marks it
  read for that agent. Admin screens and the API do not.

| Route | Name | Answer |
|---|---|---|
| `POST /{prefix}/agent/tickets/{ticket}/read` | `escalated.agent.tickets.read` | `{ "is_unread": false }` (JSON request), or back |
| `POST /{prefix}/agent/tickets/{ticket}/unread` | `escalated.agent.tickets.unread` | `{ "is_unread": true }` (JSON request), or back |
| `POST /{prefix}/agent/tickets/bulk` | `escalated.agent.tickets.bulk` | new actions `mark_read` and `mark_unread` (no `value`) |

All three need only `ticket.view` under roles & permissions and act only on
tickets the agent can see. Markers are personal: nobody else sees them, and
they are not ticket activity or events.

Props:

- `is_unread` on every row of `tickets.data` (TicketIndex) and on `ticket`
  (TicketShow, always `false` since the visit read it);
- `inbox.unread`: `{ mine, unassigned, all }`, the open tickets in each queue
  that are unread for the agent. Like the rest of `inbox` it is cached for
  `escalated.agent_inbox.cache_seconds`.

Neither is sent in cloud mode, where tickets are not stored locally.

## Customer online status

Agent pages show whether the customer is on the site right now:

- `requester_online` (boolean) and `requester_last_seen_at` (ISO 8601 or
  `null`) on every row of `tickets.data` (TicketIndex) and on `ticket`
  (TicketShow), from one query per page.
- The customer is the ticket's requester, or its contact for a guest ticket.
- Last-seen is recorded from requests the customer already makes: any
  customer-portal page while signed in (staff are not recorded), the widget's
  live chat (start, reading and sending messages, typing, ending, rating),
  and the widget's ticket status lookup. It is written at most once per
  customer every 30 seconds, into `escalated_customer_presence`.
- Online means seen within `escalated.presence.online_window_seconds` (env
  `ESCALATED_PRESENCE_ONLINE_WINDOW`, default 120).

The fields are only on agent pages. No customer, guest, widget or public API
response carries them. There is no widget heartbeat: a customer reading a
ticket without polling goes "offline" after the window.
