<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Mail\NotificationThreading;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTicketNotification extends Notification implements ShouldQueue
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
        $url = url(config('escalated.routes.prefix').'/'.$ticket->reference);

        return (new MailMessage)
            ->subject(__('escalated::notifications.new_ticket.subject', [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
            ]))
            ->markdown('escalated::emails.new-ticket', [
                'ticket' => $ticket,
                'url' => $url,
                'logoUrl' => EscalatedSettings::get('email_logo_url'),
                'accentColor' => EscalatedSettings::get('email_accent_color', '#2d3748'),
                'footerText' => EscalatedSettings::get('email_footer_text'),
            ])
            ->withSymfonyMessage(function ($message) use ($ticket) {
                NotificationThreading::applyAnchor($message, $ticket);
            });
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_ticket',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
        ];
    }
}
