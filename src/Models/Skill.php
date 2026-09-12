<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'routing_tag_ids' => 'array',
            'routing_department_ids' => 'array',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('skills');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            Escalated::userModel(),
            Escalated::table('agent_skill'),
            'skill_id',
            'user_id'
        )->withPivot('proficiency');
    }

    protected static function booted(): void
    {
        static::creating(function (self $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });

        static::saving(function (self $skill) {
            $skill->routing_tag_ids = array_values(array_unique(array_map('intval', $skill->routing_tag_ids ?? [])));
            $skill->routing_department_ids = array_values(array_unique(array_map('intval', $skill->routing_department_ids ?? [])));
        });
    }
}
