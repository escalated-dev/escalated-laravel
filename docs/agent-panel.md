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
