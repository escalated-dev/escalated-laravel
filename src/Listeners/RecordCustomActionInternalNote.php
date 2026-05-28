<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events\TicketCustomActionTriggered;
use Escalated\Laravel\Models\Reply;

class RecordCustomActionInternalNote
{
    public function handle(TicketCustomActionTriggered $event): void
    {
        $author = $event->user;
        $authorName = $author->name ?? $author->email ?? 'Unknown user';

        Reply::create([
            'ticket_id' => $event->ticket->id,
            'author_type' => method_exists($author, 'getMorphClass') ? $author->getMorphClass() : null,
            'author_id' => $author->getAuthIdentifier(),
            'body' => sprintf('Custom action "%s" was triggered by %s.', $event->action, $authorName),
            'is_internal_note' => true,
            'is_pinned' => false,
            'type' => 'note',
            'metadata' => [
                'system_note' => true,
                'custom_action' => $event->action,
                'custom_action_payload' => $event->payload,
                'custom_action_metadata' => $event->metadata,
            ],
        ]);
    }
}
