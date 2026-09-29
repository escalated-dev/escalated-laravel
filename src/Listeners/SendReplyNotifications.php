<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Notifications\TicketReplyNotification;
use Escalated\Laravel\Support\ImportContext;

class SendReplyNotifications
{
    public function handle(ReplyCreated $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        $reply = $event->reply;
        $ticket = $reply->ticket;

        // If agent replied, notify the customer
        if ($ticket->requester && ! $ticket->isRequesterIdentity($reply->author_type, $reply->author_id)) {
            $ticket->requester->notify(new TicketReplyNotification($reply));
        }

        // If customer replied, notify the assigned agent
        $hostAuthor = $reply->author_type === Escalated::newUserModel()->getMorphClass();
        if ($ticket->assigned_to && $ticket->assignee && (! $hostAuthor || (string) $reply->author_id !== (string) $ticket->assigned_to)) {
            $ticket->assignee->notify(new TicketReplyNotification($reply));
        }

        // Notify followers (except the reply author)
        $ticket->loadMissing('followers');
        foreach ($ticket->followers as $follower) {
            if ((! $hostAuthor || (string) $follower->getKey() !== (string) $reply->author_id)
                && $follower->getKey() !== $ticket->requester_id
                && $follower->getKey() !== $ticket->assigned_to) {
                $follower->notify(new TicketReplyNotification($reply));
            }
        }
    }
}
