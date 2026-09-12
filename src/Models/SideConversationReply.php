<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SideConversationReply extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('side_conversation_replies');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sideConversation(): BelongsTo
    {
        return $this->belongsTo(SideConversation::class, 'side_conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'author_id');
    }
}
