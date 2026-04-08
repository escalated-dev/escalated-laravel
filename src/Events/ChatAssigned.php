<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatAssigned implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(public ChatSession $session) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('escalated.chat.'.$this->session->id),
            new PrivateChannel('escalated.chat.queue'),
        ];

        if ($this->session->agent_id) {
            $channels[] = new PrivateChannel('escalated.agents.'.$this->session->agent_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'chat.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'agent_id' => $this->session->agent_id,
            'agent_name' => $this->session->agent?->name,
            'status' => $this->session->status->value,
        ];
    }
}
