# Ticket Contexts

Ticket contexts let a ticket reference additional host-application models that
provide useful support context. The requester remains the ticket owner through
the existing `Ticketable` relationship, while contexts represent related records
such as a project, computer, vehicle, subscription, order, asset, or account.

## Database Model

Escalated stores context records in the `escalated_ticket_contexts` table.

Each row belongs to a ticket and morphs to an attachable model:

```php
$ticket->contexts(); // HasMany<TicketContext>
$context->attachable(); // MorphTo
```

The attachable id is stored as a string so host applications can attach models
with integer, UUID, ULID, or custom string primary keys.

## Attachable Contract

Any model that should appear as ticket context must implement
`Escalated\Laravel\Contracts\TicketAttachable`.

```php
use Escalated\Laravel\Contracts\TicketAttachable;
use Illuminate\Database\Eloquent\Model;

class Project extends Model implements TicketAttachable
{
    public function ticketAttachableUrl(): ?string
    {
        return route('projects.show', $this);
    }

    public function ticketAttachableTitle(): string
    {
        return $this->name;
    }

    public function ticketAttachableSubtitle(): ?string
    {
        return $this->client?->name;
    }

    public function ticketAttachableColor(): string
    {
        return '#2563eb';
    }

    public function ticketAttachableIcon(): string
    {
        return 'folder-kanban';
    }

    public function ticketAttachableBadge(): ?string
    {
        return $this->status;
    }

    public function ticketAttachableMetadata(): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
        ];
    }
}
```

## Contract Fields

- `ticketAttachableUrl()` returns the destination URL for the UI link, or `null`
  when the context should display without a link.
- `ticketAttachableTitle()` returns the primary display text.
- `ticketAttachableSubtitle()` returns secondary display text, or `null`.
- `ticketAttachableColor()` returns a CSS-compatible color for the UI accent.
- `ticketAttachableIcon()` returns the icon name the UI should render.
- `ticketAttachableBadge()` returns an optional small label.
- `ticketAttachableMetadata()` returns structured data for custom UIs or plugins.

## Attaching Context

Use `attachContext()` on a ticket to connect an attachable model:

```php
use Escalated\Laravel\Models\Ticket;

$ticket = Ticket::where('reference', 'ESC-00042')->firstOrFail();
$project = Project::findOrFail($projectId);

$ticket->attachContext($project, [
    'label' => 'Affected project',
    'metadata' => ['source' => 'intake-form'],
    'sort_order' => 10,
]);
```

`attachContext()` is idempotent for the same ticket and attachable model. Calling
it again returns the existing context record instead of creating a duplicate.

## Detaching Context

```php
$ticket->detachContext($project);
```

This removes the context record only. It does not delete the attached host model.

## API Payload

When a ticket is loaded with `contexts.attachable`, the ticket resource includes
a `contexts` array:

```json
[
    {
        "id": 1,
        "type": "App\\Models\\Project",
        "key": "42",
        "label": "Affected project",
        "display": {
            "url": "https://app.test/projects/42",
            "title": "Website rebuild",
            "subtitle": "Acme Co.",
            "color": "#2563eb",
            "icon": "folder-kanban",
            "badge": "active",
            "metadata": {
                "code": "WEB-24",
                "status": "active"
            }
        },
        "metadata": {
            "source": "intake-form"
        },
        "sort_order": 10
    }
]
```

The built-in agent and API ticket show endpoints eager-load contexts with their
attachable models.
