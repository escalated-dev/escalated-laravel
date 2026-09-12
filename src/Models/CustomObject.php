<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomObject extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('custom_objects');
    }

    protected function casts(): array
    {
        return [
            'fields_schema' => 'array',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(CustomObjectRecord::class, 'object_id');
    }
}
