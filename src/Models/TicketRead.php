<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Services\TicketReads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When an agent last read a ticket. `last_read_at` NULL means the agent
 * marked it unread. See {@see TicketReads}.
 */
class TicketRead extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return Escalated::table('ticket_reads');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
