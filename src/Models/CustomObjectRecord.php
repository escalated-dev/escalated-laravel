<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomObjectRecord extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('custom_object_records');
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(CustomObject::class, 'object_id');
    }
}
