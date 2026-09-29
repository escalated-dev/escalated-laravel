<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatStarted implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(public ChatSession $session) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(TenantBroadcast::channel('chat.'.$this->session->id, $this->session)),
            new PrivateChannel(TenantBroadcast::channel('chat.queue', $this->session)),
        ];

        if ($this->session->agent_id) {
            $channels[] = new PrivateChannel(TenantBroadcast::channel('agents.'.$this->session->agent_id, $this->session));
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'chat.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'ticket_id' => $this->session->ticket_id,
            'status' => $this->session->status->value,
            'started_at' => $this->session->started_at->toIso8601String(),
        ];
    }
}
