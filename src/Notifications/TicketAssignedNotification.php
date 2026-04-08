<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return config('escalated.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;
        $url = url(config('escalated.routes.prefix').'/agent/tickets/'.$ticket->reference);

        return (new MailMessage)
            ->subject(__('escalated::notifications.ticket_assigned.subject', [
                'reference' => $ticket->reference,
            ]))
            ->markdown('escalated::emails.assigned', [
                'ticket' => $ticket,
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
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
        ];
    }
}
