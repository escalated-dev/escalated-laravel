<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\Concerns\GuardsTenantNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** In-app only: a live chat was transferred to the agent. */
class ChatTransferredNotification extends Notification implements ShouldQueue
{
    use GuardsTenantNotification, Queueable;

    public function __construct(
        public Ticket $ticket,
        public int|string $sessionId,
        public ?string $fromAgentName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return array_values(array_intersect(config('escalated.notifications.channels', ['mail', 'database']), ['database']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat_transferred',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'session_id' => $this->sessionId,
            'from_agent' => $this->fromAgentName,
        ];
    }
}
