<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\Concerns\GuardsTenantNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app only: an SLA target on the agent's ticket is about to be missed.
 * It sends no email (there never was one for warnings).
 */
class SlaWarningNotification extends Notification implements ShouldQueue
{
    use GuardsTenantNotification, Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $slaType,
        public int $minutesRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return array_values(array_intersect(config('escalated.notifications.channels', ['mail', 'database']), ['database']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sla_warning',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'sla_type' => $this->slaType,
            'minutes_remaining' => $this->minutesRemaining,
        ];
    }
}
