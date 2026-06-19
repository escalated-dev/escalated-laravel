<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomField extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('custom_fields');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $field) {
            if (empty($field->slug)) {
                $field->slug = Str::slug($field->name, '_');
            }
        });
    }
}
