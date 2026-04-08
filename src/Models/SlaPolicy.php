<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Database\Factories\SlaPolicyFactory;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_response_hours' => 'array',
            'resolution_hours' => 'array',
            'business_hours_only' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('sla_policies');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sla_policy_id');
    }

    public function getFirstResponseHoursFor(TicketPriority $priority): ?float
    {
        return $this->first_response_hours[$priority->value] ?? null;
    }

    public function getResolutionHoursFor(TicketPriority $priority): ?float
    {
        return $this->resolution_hours[$priority->value] ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    protected static function newFactory(): SlaPolicyFactory
    {
        return SlaPolicyFactory::new();
    }
}
