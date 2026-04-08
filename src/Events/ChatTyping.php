<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatTyping implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public ChatSession $session,
        public bool $isAgent,
        public ?string $userName = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.chat.'.$this->session->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'is_agent' => $this->isAgent,
            'user_name' => $this->userName,
        ];
    }
}
