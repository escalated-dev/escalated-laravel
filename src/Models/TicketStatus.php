<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketStatus extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('ticket_statuses');
    }

    protected static function booted(): void
    {
        static::creating(function (self $status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->label, '_');
            }
        });
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
