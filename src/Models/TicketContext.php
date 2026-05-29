<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Contracts\TicketAttachable;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketContext extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    protected $appends = ['display'];

    public function getTable(): string
    {
        return Escalated::table('ticket_contexts');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDisplayAttribute(): array
    {
        $attachable = $this->attachable;

        if (! $attachable instanceof TicketAttachable) {
            return [
                'url' => null,
                'title' => class_basename($this->attachable_type).' #'.$this->attachable_id,
                'subtitle' => null,
                'color' => null,
                'icon' => null,
                'badge' => null,
                'metadata' => [],
            ];
        }

        return [
            'url' => $attachable->ticketAttachableUrl(),
            'title' => $attachable->ticketAttachableTitle(),
            'subtitle' => $attachable->ticketAttachableSubtitle(),
            'color' => $attachable->ticketAttachableColor(),
            'icon' => $attachable->ticketAttachableIcon(),
            'badge' => $attachable->ticketAttachableBadge(),
            'metadata' => $attachable->ticketAttachableMetadata(),
        ];
    }
}
