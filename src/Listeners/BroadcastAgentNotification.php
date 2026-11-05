<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events\AgentNotificationCreated;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AgentNotificationFeed;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Support\TicketVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Broadcasts each new in-app Escalated notification for staff as
 * `notification.created` on the agent's private channel, while broadcasting
 * is enabled.
 */
class BroadcastAgentNotification
{
    public function __construct(private AgentNotificationFeed $feed) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database'
            || ! AgentNotificationCreated::broadcastingEnabled()
            || ! isset(AgentNotificationFeed::TYPES[get_class($event->notification)])
            || ! $event->response instanceof DatabaseNotification
            || ! $event->notifiable instanceof Model
            || ! StaffAccess::isStaff($event->notifiable)) {
            return;
        }

        $ticketId = $event->response->data['ticket_id'] ?? null;
        // Queued notifications run without a signed-in agent, so check the
        // recipient's visibility here rather than through the request.
        $ticket = $ticketId !== null ? Ticket::query()->withoutGlobalScopes()->find($ticketId) : null;
        $tickets = $ticket && TicketVisibility::allows($event->notifiable, $ticket) ? collect([$ticket->getKey() => $ticket]) : collect();

        AgentNotificationCreated::dispatch($event->notifiable->getKey(), $this->feed->item($event->response, $tickets));
    }
}
