<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('skills');
    }

    public function routingTags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            Escalated::table('skill_routing_tags'),
            'skill_id',
            'tag_id',
        );
    }

    public function routingDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            Escalated::table('skill_routing_departments'),
            'skill_id',
            'department_id',
        );
    }

    public function agentSkills(): HasMany
    {
        return $this->hasMany(AgentSkill::class, 'skill_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            Escalated::userModel(),
            Escalated::table('agent_skill'),
            'skill_id',
            'user_id',
        )->withPivot('proficiency')
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (self $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });

        static::updating(function (self $skill) {
            if ($skill->isDirty('name')) {
                $skill->slug = Str::slug((string) $skill->name);
            }
        });
    }
}
