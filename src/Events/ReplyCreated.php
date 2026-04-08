<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\Reply;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReplyCreated implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(public Reply $reply) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.tickets.'.$this->reply->ticket_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reply.created';
    }

    public function broadcastWith(): array
    {
        return [
            'reply_id' => $this->reply->id,
            'ticket_id' => $this->reply->ticket_id,
            'body' => $this->reply->body,
            'is_internal_note' => (bool) $this->reply->is_internal_note,
            'author_id' => $this->reply->user_id,
            'author_name' => $this->reply->user?->name ?? null,
            'created_at' => $this->reply->created_at?->toISOString(),
        ];
    }
}
