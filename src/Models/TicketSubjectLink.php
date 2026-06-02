<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Contracts\TicketSubject;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Join row linking a ticket to one host-app subject model. The `subject`
 * relation resolves polymorphically to the attached host model (a Project,
 * Customer, …), which should implement
 * {@see TicketSubject}.
 */
class TicketSubjectLink extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('ticket_subjects');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
