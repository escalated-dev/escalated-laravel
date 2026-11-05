<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

/**
 * When a customer (a requester, or a guest's contact) was last seen by the
 * customer portal or the widget. Only ever shown to staff.
 */
class CustomerPresence extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return Escalated::table('customer_presence');
    }
}
