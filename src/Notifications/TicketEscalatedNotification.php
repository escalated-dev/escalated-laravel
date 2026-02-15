<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return config('escalated.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('escalated::notifications.ticket_escalated.subject', [
                'reference' => $this->ticket->reference,
                'subject' => $this->ticket->subject,
            ]))
            ->line(__('escalated::notifications.ticket_escalated.line1'))
            ->line(__('escalated::notifications.ticket_escalated.subject_line', ['subject' => $this->ticket->subject]))
            ->line(__('escalated::notifications.ticket_escalated.priority_line', ['priority' => $this->ticket->priority->label()]));

        if ($this->reason) {
            $mail->line(__('escalated::notifications.ticket_escalated.reason_line', ['reason' => $this->reason]));
        }

        return $mail
            ->action(__('escalated::notifications.ticket_escalated.action'), url(config('escalated.routes.prefix').'/agent/tickets/'.$this->ticket->reference))
            ->line(__('escalated::notifications.ticket_escalated.closing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_escalated',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'reason' => $this->reason,
        ];
    }
}
