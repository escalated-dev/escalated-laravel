<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

class SlackInboundEvent extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'available_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return Escalated::table('slack_events');
    }
}
