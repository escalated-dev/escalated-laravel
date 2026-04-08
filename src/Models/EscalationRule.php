<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Database\Factories\EscalationRuleFactory;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscalationRule extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
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
