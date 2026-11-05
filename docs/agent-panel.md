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
