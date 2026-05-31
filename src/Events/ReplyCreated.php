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
        // The author is a polymorphic Ticketable (author_type/author_id), not a
        // `user` — read it from the `author` relation. Prefer the Ticketable
        // display name so both Users and Contacts resolve, falling back to a
        // plain `name` attribute.
        $author = $this->reply->author;
        $authorName = $author?->ticketable_name ?? $author?->name;

        return [
            'reply_id' => $this->reply->id,
            'ticket_id' => $this->reply->ticket_id,
            'body' => $this->reply->body,
            'is_internal_note' => (bool) $this->reply->is_internal_note,
            'author_id' => $author?->getKey(),
            'author_name' => $authorName,
            // Nested shape mirrors ReplyResource so a real-time consumer can
            // render reply.author.name without a server round-trip.
            'author' => $author ? [
                'id' => $author->getKey(),
                'name' => $authorName,
            ] : null,
            'created_at' => $this->reply->created_at?->toISOString(),
        ];
    }
}
