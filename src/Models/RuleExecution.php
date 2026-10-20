<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

/**
 * Records that an escalation rule or automation acted on a ticket, so the
 * periodic sweeps fire each rule once per ticket instead of on every run.
 */
class RuleExecution extends Model
{
    use UsesEscalatedConnection;

    public const ESCALATION_RULE = 'escalation_rule';

    public const AUTOMATION = 'automation';

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('rule_executions');
    }

    protected function casts(): array
    {
        return [
            'rule_id' => 'integer',
            'ticket_id' => 'integer',
            'fired_at' => 'datetime',
        ];
    }
}
