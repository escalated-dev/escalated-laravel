<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\InternalNoteAdded;
use Escalated\Laravel\Events\ReplyCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reply extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted()
    {
        parent::booted();

        static::created(function ($reply) {
            if ($reply->is_internal_note) {
                InternalNoteAdded::dispatch($reply);
            }else{
                ReplyCreated::dispatch($reply);
            }
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

    protected static function newFactory(): \Escalated\Laravel\Database\Factories\ReplyFactory
    {
        return \Escalated\Laravel\Database\Factories\ReplyFactory::new();
    }
}
