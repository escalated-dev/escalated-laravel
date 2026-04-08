<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Database\Factories\ChatSessionFactory;
use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSession extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('chat_sessions');
    }

    protected function casts(): array
    {
        return [
            'status' => ChatSessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'customer_typing_at' => 'datetime',
            'agent_typing_at' => 'datetime',
            'metadata' => 'array',
            'rating' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'agent_id');
    }

    // Scopes

    public function scopeWaiting($query)
    {
        return $query->where('status', ChatSessionStatus::Waiting->value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', ChatSessionStatus::Active->value);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', ChatSessionStatus::Ended->value);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    protected static function newFactory(): ChatSessionFactory
    {
        return ChatSessionFactory::new();
    }
}
