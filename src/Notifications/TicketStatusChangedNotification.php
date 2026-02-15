<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public TicketStatus $oldStatus,
        public TicketStatus $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return config('escalated.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('escalated::notifications.ticket_status_changed.subject', [
                'reference' => $this->ticket->reference,
                'status' => $this->newStatus->label(),
            ]))
            ->line(__('escalated::notifications.ticket_status_changed.line1'))
            ->line(__('escalated::notifications.ticket_status_changed.from_line', ['status' => $this->oldStatus->label()]))
            ->line(__('escalated::notifications.ticket_status_changed.to_line', ['status' => $this->newStatus->label()]))
            ->action(__('escalated::notifications.ticket_status_changed.action'), url(config('escalated.routes.prefix').'/'.$this->ticket->reference))
            ->line(__('escalated::notifications.ticket_status_changed.closing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'status_changed',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
        ];
    }
}
