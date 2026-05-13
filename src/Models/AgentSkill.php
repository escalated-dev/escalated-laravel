<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSkill extends Model
{
    protected $fillable = ['user_id', 'skill_id', 'proficiency'];

    protected function casts(): array
    {
        return [
            'proficiency' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('agent_skill');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'user_id');
    }
}
