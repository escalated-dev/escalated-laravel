<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mention extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('mentions');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(Reply::class, 'reply_id');
    }

    public function user(): BelongsTo
    {
        $userModel = Escalated::userModel();

        return $this->belongsTo($userModel, 'user_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
