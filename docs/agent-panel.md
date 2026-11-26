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

## Live chat

Agents run their own chats through agent routes. They take the same bodies as
the admin `escalated.admin.chat.*` routes, which are unchanged and still
admin-only.

| Route | Name | Who | Body | Answer |
|---|---|---|---|---|
| `GET /{prefix}/agent/chat/active` | `escalated.agent.chat.active` | any agent | | `{ "sessions": [chat] }`, the agent's own active chats |
| `GET /{prefix}/agent/chat/queue` | `escalated.agent.chat.queue` | any agent | | `{ "sessions": [chat] }`, waiting chats, oldest first |
| `POST /{prefix}/agent/chat/{session}/accept` | `escalated.agent.chat.accept` | any agent who can see the ticket | | `{ "message", "session" }`; 422 when the chat is not waiting |
| `POST /{prefix}/agent/chat/{session}/end` | `escalated.agent.chat.end` | the chat's agent, admins | | `{ "message" }`; closes the ticket |
| `POST /{prefix}/agent/chat/{session}/transfer` | `escalated.agent.chat.transfer` | the chat's agent, admins | `{ "agent_id" }` or `{ "department_id" }` | `{ "message", "session" }`; 422 when the agent is not staff or cannot see the ticket |
| `POST /{prefix}/agent/chat/{session}/message` | `escalated.agent.chat.message` | the chat's agent, admins | `{ "body", "is_internal_note"? }` | `{ "message", "reply_id" }` |
| `POST /{prefix}/agent/chat/{session}/typing` | `escalated.agent.chat.typing` | the chat's agent, admins | | `{ "message" }` |

`{session}` is the numeric chat session id (`ticket.chat_session_id`), not the
visitor's widget token. Another agent gets a 403, a chat on a ticket the agent
cannot see a 404, and message or typing on an ended chat a 422.

- A message is written as the signed-in agent and broadcast as `chat.message`.
  With `is_internal_note: true` it is an internal note on the ticket instead and
  is not sent to the visitor (the admin message route does the same).
- Typing broadcasts `chat.typing` with the name of the agent typing in
  `user_name` (the admin route now does too).
- With roles & permissions on, `active` and `queue` need `ticket.view`, the
  rest `reply.create`.

While live chat is on (`chat_enabled`), agents get these shared props in
`escalated`; while it is off only `chat_enabled: false` is sent:

- `chat_enabled`: whether live chat is on (sent to everyone);
- `active_chats`: the agent's own active chats, newest first, at most 25;
- `chat_queue`: waiting chats the agent can see, oldest first, at most 25.

A `chat` is `{ id, status, agent_id, ticket_id, ticket_reference, subject,
customer_name, customer_email, department: { id, name } | null, first_message,
last_message, last_message_at, started_at, created_at }`. `id` is the chat
session id.

The agent ticket list takes `live_chat=1` (`escalated.agent.tickets.index`),
which lists the chats in progress.

### Who wrote a chat line

A chat line is the agent's when it has an author who is not the ticket's
requester (`Ticket::isChatAgentReply()`). This is decided by identity, so it
works when staff and customers are the same user model. On a ticket that has a
requester, such as a widget chat under `guest_policy_mode = guest_user`, the
visitor's messages (the opening one included) are written as the requester's.
The reply listeners then treat them as the customer's own: they do not count as
the first response and do not notify the requester. The widget and the
`chat.message` event name the visitor with the name they gave.

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

## Notifications

The bell in the agent panel lists the agent's in-app notifications. They are
Laravel database notifications in the host's `notifications` table, which
Escalated already writes through the `database` channel in
`escalated.notifications.channels` (on by default). The feed lists only
Escalated's own notification classes.

The feed is **available** while `database` is one of the channels and the
`notifications` table exists. Otherwise the routes answer 404 and the shared
prop is left out, so the bell stays hidden.

| `type` | Sent to | When |
|---|---|---|
| `ticket_assigned` | the assignee | someone else assigns them a ticket (assigning yourself sends nothing, email included) |
| `ticket_reply` | the assignee and followers | a public reply from the customer side; other agents' replies still email but are not in the feed |
| `mention` | the mentioned agent | an @mention in an internal note |
| `sla_warning` | the assignee | `sla.warning`; in-app only, no email |
| `sla_breached` | the assignee | `sla.breached` |
| `chat_transferred` | the receiving agent | a live chat is transferred to them; in-app only |
| `new_ticket`, `ticket_escalated`, `status_changed` | as before | the existing notifications, also listed |

| Route | Name | Answer |
|---|---|---|
| `GET /{prefix}/agent/notifications` | `escalated.agent.notifications.index` | `{ data: [item], meta: { current_page, last_page, per_page, total }, unread_count }`, newest first; `?per_page=` (default 20, max 50), `?unread=1`, `?page=` |
| `POST /{prefix}/agent/notifications/{id}/read` | `escalated.agent.notifications.read` | `{ unread_count }`; 404 for another user's notification |
| `POST /{prefix}/agent/notifications/read-all` | `escalated.agent.notifications.read-all` | `{ unread_count: 0 }` |

An item is `{ id, type, title, body, ticket: { id, reference, subject } | null, url, created_at, read_at }`.
`title` is translated when it is read (`escalated::messages.agent_notifications.*`).
`body` is the ticket's current subject. If the agent can no longer see the
ticket (visibility, department, or the ticket was deleted), the item stays in
the list but its `ticket`, `url` and `body` are `null` and its title is
generic.

Agents get `escalated.notifications: { unread_count }` in the shared props.
While `escalated.broadcasting.enabled` is on, each new item is broadcast as
`notification.created` on the agent's private `escalated.agents.{id}` channel,
with the item as the payload.

The routes need no permission under roles & permissions: they are personal.
Muting notification types per agent is not built yet.

## Real-time updates

The agent screens update live through Laravel Echo. Three things are needed:

1. `ESCALATED_BROADCASTING_ENABLED=true` (`escalated.broadcasting.enabled`).
   While it is off no event is broadcast and the `escalated.*` channels have
   no authorisation, so subscriptions fail.
2. A broadcasting driver in your app (Reverb, Pusher, Ably, Soketi, ...).
3. Echo in the browser. The frontend looks for `window.Echo`. If your app keeps
   its Echo instance in a module instead, pass it to the plugin (frontend
   0.12.12 and later):

   ```js
   import echo from './echo';

   app.use(EscalatedPlugin, { echo });
   ```

Without Echo:

- the agent ticket list reloads itself every 30 seconds (frontend 0.12.12 and
  later; `app.use(EscalatedPlugin, { pollInterval: 0 })` turns that off, or
  pass another number of milliseconds);
- an open ticket, live chat (new chats, messages, typing) and the
  notification bell update only when the page is reloaded. Live chat needs
  broadcasting.

The support widget does not use Echo: it polls for chat messages on its own.
