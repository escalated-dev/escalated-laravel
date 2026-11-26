<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A host user with split names and a role column instead of `name`,
 * `is_agent` and `is_admin` (the schema in issue #316).
 */
class SplitNameUser extends Authenticatable implements Ticketable
{
    use HasTickets, Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public function getTicketableNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getTicketableEmailAttribute(): string
    {
        return $this->email;
    }
}
