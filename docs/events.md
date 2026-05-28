# Events

Escalated dispatches events for every ticket action. You can listen to these in your application.

## Available Events

| Event | Dispatched When |
|-------|----------------|
| `TicketCreated` | New ticket is created |
| `TicketUpdated` | Ticket details are updated |
| `TicketStatusChanged` | Status transitions (with old/new status) |
| `TicketAssigned` | Ticket is assigned to an agent |
| `TicketUnassigned` | Ticket is unassigned |
| `TicketPriorityChanged` | Priority is changed |
| `TicketEscalated` | Ticket is escalated |
| `TicketResolved` | Ticket is resolved |
| `TicketClosed` | Ticket is closed |
| `TicketReopened` | Ticket is reopened |
| `ReplyCreated` | Public reply is added |
| `InternalNoteAdded` | Internal note is added |
| `SlaBreached` | SLA deadline is breached |
| `SlaWarning` | SLA breach is approaching |
| `TagAddedToTicket` | Tag is added |
| `TagRemovedFromTicket` | Tag is removed |
| `DepartmentChanged` | Department is changed |
| `TicketCustomActionTriggered` | Agent clicks a configured custom ticket action |

## Listening to Events

Register listeners in your `EventServiceProvider`:

```php
use Escalated\Laravel\Events\TicketCreated;

protected $listen = [
    TicketCreated::class => [
        \App\Listeners\NotifySlack::class,
        \App\Listeners\CreateJiraIssue::class,
    ],
];
```

Or use closures:

```php
Event::listen(TicketCreated::class, function ($event) {
    Log::info('New ticket: ' . $event->ticket->reference);
});
```

## Event Payloads

### TicketCreated
- `$event->ticket` — The created Ticket model

### TicketStatusChanged
- `$event->ticket` — The Ticket model
- `$event->oldStatus` — Previous TicketStatus enum
- `$event->newStatus` — New TicketStatus enum
- `$event->causer` — User who made the change (nullable)

### TicketAssigned
- `$event->ticket` — The Ticket model
- `$event->agentId` — ID of the assigned agent
- `$event->causer` — User who assigned (nullable)

### SlaBreached
- `$event->ticket` — The Ticket model
- `$event->type` — `'first_response'` or `'resolution'`

### ReplyCreated
- `$event->reply` — The Reply model (with `ticket` relationship)

### TicketCustomActionTriggered
- `$event->ticket` — The Ticket model
- `$event->action` — Configured action key, such as `sync-crm`
- `$event->user` — Agent who clicked the action
- `$event->payload` — Optional array submitted by the UI or API
- `$event->metadata` — Metadata from the configured action
