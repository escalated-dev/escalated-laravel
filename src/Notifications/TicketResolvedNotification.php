<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return config('escalated.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('escalated::notifications.ticket_resolved.subject', [
                'reference' => $this->ticket->reference,
            ]))
            ->line(__('escalated::notifications.ticket_resolved.line1'))
            ->line(__('escalated::notifications.ticket_resolved.subject_line', ['subject' => $this->ticket->subject]))
            ->line(__('escalated::notifications.ticket_resolved.reopen_line'))
            ->action(__('escalated::notifications.ticket_resolved.action'), url(config('escalated.routes.prefix').'/'.$this->ticket->reference))
            ->line(__('escalated::notifications.ticket_resolved.closing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_resolved',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
        ];
    }
}
