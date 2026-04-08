<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
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
            new PrivateChannel('escalated.chat.'.$this->session->id),
        ];

        if ($this->toAgentId) {
            $channels[] = new PrivateChannel('escalated.agents.'.$this->toAgentId);
        }

        if ($this->fromAgentId) {
            $channels[] = new PrivateChannel('escalated.agents.'.$this->fromAgentId);
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
