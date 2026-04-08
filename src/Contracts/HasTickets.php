<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;

trait HasTickets
{
    use Notifiable;

    public function escalatedTickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'requester');
    }

    public function escalatedAssignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function getTicketableNameAttribute(): string
    {
        $column = config('escalated.user_display_column', 'name');

        return $this->getAttribute($column)
            ?? $this->getAttribute('email')
            ?? 'Unknown';
    }

    public function getTicketableEmailAttribute(): string
    {
        return $this->email ?? '';
    }
}
