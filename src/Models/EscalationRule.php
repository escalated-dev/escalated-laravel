<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Database\Factories\EscalationRuleFactory;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Services\RuleFireLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscalationRule extends Model
{
    use HasFactory, UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'repeat_after_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $rule) => app(RuleFireLog::class)->forget(RuleExecution::ESCALATION_RULE, $rule->getKey()));
    }

    public function getTable(): string
    {
        return Escalated::table('escalation_rules');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    protected static function newFactory(): EscalationRuleFactory
    {
        return EscalationRuleFactory::new();
    }
}
