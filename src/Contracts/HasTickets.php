<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTickets
{
    public function escalatedTickets(): MorphMany
    {
        return $this->morphMany(\Escalated\Laravel\Models\Ticket::class, 'requester');
    }

    public function escalatedAssignedTickets(): HasMany
    {
        return $this->hasMany(\Escalated\Laravel\Models\Ticket::class, 'assigned_to');
    }

    public function getTicketableNameAttribute(): string
    {
        $column = config('escalated-laravel.user_name_column', 'name');

        return $this->getAttribute($column)
            ?? $this->getAttribute('email')
            ?? 'Unknown';
    }

    public function getTicketableEmailAttribute(): string
    {
        return $this->email ?? '';
    }
}
