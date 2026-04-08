<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Database\Factories\TagFactory;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('tags');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, Escalated::table('ticket_tag'), 'tag_id', 'ticket_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }
}
