<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $dispatchesEvents = [
        'created' => \Escalated\Laravel\Events\InternalNoteAdded::class,
    ];

    protected static function booted()
    {
        // Automatically filter queries for cars
        static::addGlobalScope('is_internal_note', function (Builder $builder) {
            $builder->where('is_internal_note', true);
        });

        // Automatically set the type when creating a new Car
        static::creating(function ($internalNote) {
            $internalNote->is_internal_note = true;
        });
    }

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
            'is_pinned' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function getTable(): string
    {
        return Escalated::table('replies');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    protected static function newFactory(): \Escalated\Laravel\Database\Factories\InternalNoteFactory
    {
        return \Escalated\Laravel\Database\Factories\InternalNoteFactory::new();
    }
}
