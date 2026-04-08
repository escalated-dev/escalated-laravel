<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Reply;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessage implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public ChatSession $session,
        public Reply $reply,
        public bool $isAgent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.chat.'.$this->session->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'reply_id' => $this->reply->id,
            'body' => $this->reply->body,
            'author' => $this->reply->author?->name ?? 'Customer',
            'is_agent' => $this->isAgent,
            'created_at' => $this->reply->created_at->toIso8601String(),
        ];
    }
}
