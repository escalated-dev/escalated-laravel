<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SideConversation extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('side_conversations');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'created_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SideConversationReply::class, 'side_conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
