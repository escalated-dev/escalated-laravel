<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\EscalatedSettings;
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
        $ticket = $this->ticket;
        $url = url(config('escalated.routes.prefix').'/'.$ticket->reference);

        return (new MailMessage)
            ->subject(__('escalated::notifications.ticket_status_changed.subject', [
                'reference' => $ticket->reference,
                'status' => $this->newStatus->label(),
            ]))
            ->markdown('escalated::emails.status-changed', [
                'ticket' => $ticket,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'url' => $url,
                'logoUrl' => EscalatedSettings::get('email_logo_url'),
                'accentColor' => EscalatedSettings::get('email_accent_color', '#2d3748'),
                'footerText' => EscalatedSettings::get('email_footer_text'),
            ])
            ->withSymfonyMessage(function ($message) use ($ticket) {
                $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'escalated.dev';
                $threadId = 'ticket-'.$ticket->id.'@'.$domain;
                $message->getHeaders()->addIdHeader('In-Reply-To', $threadId);
                $message->getHeaders()->addIdHeader('References', $threadId);
            });
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
