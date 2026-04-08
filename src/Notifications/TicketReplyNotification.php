<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\EscalatedSettings;
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
        $url = url(config('escalated.routes.prefix').'/'.$ticket->reference);

        return (new MailMessage)
            ->subject(__('escalated::notifications.ticket_reply.subject', [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
            ]))
            ->markdown('escalated::emails.reply', [
                'ticket' => $ticket,
                'reply' => $this->reply,
                'url' => $url,
                'logoUrl' => EscalatedSettings::get('email_logo_url'),
                'accentColor' => EscalatedSettings::get('email_accent_color', '#2d3748'),
                'footerText' => EscalatedSettings::get('email_footer_text'),
            ])
            ->withSymfonyMessage(function ($message) use ($ticket) {
                $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'escalated.dev';
                $messageId = '<ticket-'.$ticket->id.'@'.$domain.'>';
                $message->getHeaders()->addTextHeader('In-Reply-To', $messageId);
                $message->getHeaders()->addTextHeader('References', $messageId);
            });
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
