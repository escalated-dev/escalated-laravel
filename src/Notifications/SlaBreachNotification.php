<?php

namespace Escalated\Laravel\Notifications;

use Escalated\Laravel\Models\EscalatedSettings;
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
        $ticket = $this->ticket;
        $url = url(config('escalated.routes.prefix').'/agent/tickets/'.$ticket->reference);

        return (new MailMessage)
            ->subject(__('escalated::notifications.sla_breach.subject', [
                'reference' => $ticket->reference,
                'type' => $this->breachType === 'first_response'
                    ? __('escalated::notifications.sla_breach.type_first_response')
                    : __('escalated::notifications.sla_breach.type_resolution'),
            ]))
            ->markdown('escalated::emails.sla-breach', [
                'ticket' => $ticket,
                'breachType' => $this->breachType,
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
            'type' => 'sla_breach',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'breach_type' => $this->breachType,
        ];
    }
}
