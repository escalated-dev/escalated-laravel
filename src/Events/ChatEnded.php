<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatEnded implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public ChatSession $session,
        public string $endedBy = 'system'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.chat.'.$this->session->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'ended_by' => $this->endedBy,
            'ended_at' => $this->session->ended_at?->toIso8601String(),
        ];
    }
}
