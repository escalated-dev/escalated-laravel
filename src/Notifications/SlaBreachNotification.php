<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $breachType,
    ) {}

    public function via(object $notifiable): array
    {
        return config('escalated.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = $this->breachType === 'first_response'
            ? __('escalated::notifications.sla_breach.type_first_response')
            : __('escalated::notifications.sla_breach.type_resolution');

        return (new MailMessage)
            ->subject(__('escalated::notifications.sla_breach.subject', [
                'reference' => $this->ticket->reference,
                'type' => $typeLabel,
            ]))
            ->line(__('escalated::notifications.sla_breach.line1', ['reference' => $this->ticket->reference]))
            ->line(__('escalated::notifications.sla_breach.type_line', ['type' => $typeLabel]))
            ->line(__('escalated::notifications.sla_breach.subject_line', ['subject' => $this->ticket->subject]))
            ->line(__('escalated::notifications.sla_breach.priority_line', ['priority' => $this->ticket->priority->label()]))
            ->action(__('escalated::notifications.sla_breach.action'), url(config('escalated.routes.prefix').'/agent/tickets/'.$this->ticket->reference))
            ->line(__('escalated::notifications.sla_breach.closing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sla_breach',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'breach_type' => $this->breachType,
        ];
    }
}
