<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\Reply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification implements ShouldQueue
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
        $authorName = $this->reply->author?->name ?? 'Someone';
        $url = url(config('escalated.routes.prefix', 'support').'/'.$ticket->reference);

        return (new MailMessage)
            ->subject("You were mentioned in ticket #{$ticket->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$authorName} mentioned you in ticket #{$ticket->reference}: {$ticket->subject}")
            ->action('View Ticket', $url)
            ->line('Thank you for using our support system.');
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->reply->ticket;

        return [
            'type' => 'mention',
            'ticket_id' => $ticket->id,
            'reply_id' => $this->reply->id,
            'reference' => $ticket->reference ?? null,
            'mentioned_by' => $this->reply->author?->name ?? 'Someone',
        ];
    }
}
