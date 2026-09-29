<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatTransferred implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public ChatSession $session,
        public ?int $fromAgentId = null,
        public ?int $toAgentId = null,
        public ?int $toDepartmentId = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(TenantBroadcast::channel('chat.'.$this->session->id, $this->session)),
        ];

        if ($this->toAgentId) {
            $channels[] = new PrivateChannel(TenantBroadcast::channel('agents.'.$this->toAgentId, $this->session));
        }

        if ($this->fromAgentId) {
            $channels[] = new PrivateChannel(TenantBroadcast::channel('agents.'.$this->fromAgentId, $this->session));
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'chat.transferred';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'from_agent_id' => $this->fromAgentId,
            'to_agent_id' => $this->toAgentId,
            'to_department_id' => $this->toDepartmentId,
        ];
    }
}
