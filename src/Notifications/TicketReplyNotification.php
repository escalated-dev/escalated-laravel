<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Reply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reply $reply) {}

    public function via(object $notifiable): array
    {
        return config('escalated.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->reply->ticket;

        return (new MailMessage)
            ->subject(__('escalated::notifications.ticket_reply.subject', [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
            ]))
            ->line(__('escalated::notifications.ticket_reply.line1'))
            ->line($this->reply->body)
            ->action(__('escalated::notifications.ticket_reply.action'), url(config('escalated.routes.prefix').'/'.$ticket->reference))
            ->line(__('escalated::notifications.ticket_reply.closing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_reply',
            'ticket_id' => $this->reply->ticket_id,
            'reply_id' => $this->reply->id,
            'reference' => $this->reply->ticket->reference ?? null,
        ];
    }
}
