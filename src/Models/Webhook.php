<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Support\EventVocabulary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use UsesEscalatedConnection;

    /**
     * Legacy event names (ADR 2026-10-21-event-vocabulary), including three
     * the admin form used to offer. Rows saved with them still match.
     */
    public const LEGACY_EVENT_NAMES = EventVocabulary::LEGACY;

    protected $guarded = ['id'];

    /**
     * The signing secret is used to sign deliveries and never serialized.
     * Pages see `has_secret` instead.
     */
    protected $hidden = ['secret'];

    protected $appends = ['has_secret'];

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

    public function getHasSecretAttribute(): bool
    {
        return filled($this->attributes['secret'] ?? null);
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
