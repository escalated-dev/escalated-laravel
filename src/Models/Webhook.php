<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use UsesEscalatedConnection;

    /**
     * Names the admin form used to offer for three events, which never
     * matched the names those events are dispatched under. Rows saved with
     * them still match.
     */
    public const LEGACY_EVENT_NAMES = [
        'internal_note.added' => 'note.created',
        'tag.added' => 'ticket.tag_added',
        'tag.removed' => 'ticket.tag_removed',
    ];

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('webhooks');
    }

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function subscribedTo(string $event): bool
    {
        return in_array($event, $this->subscribedEvents(), true);
    }

    /**
     * The event names this webhook is subscribed to, with legacy names
     * translated to the names the events are dispatched under.
     *
     * @return list<string>
     */
    public function subscribedEvents(): array
    {
        return array_values(array_unique(array_map(
            fn ($name) => self::LEGACY_EVENT_NAMES[$name] ?? $name,
            (array) ($this->events ?? []),
        )));
    }
}
