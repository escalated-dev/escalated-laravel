<?php

namespace Escalated\Laravel\Mail;

use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Services\Newsletter\NewsletterRendererService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NewsletterDelivery $delivery) {}

    public function envelope(): Envelope
    {
        $n = $this->delivery->newsletter;

        return new Envelope(
            from: new Address($n->from_email, $n->from_name ?? null),
            replyTo: $n->reply_to ? [new Address($n->reply_to)] : [],
            subject: $this->resolveSubject(),
        );
    }

    public function headers(): Headers
    {
        $delivery = $this->delivery;
        $unsub = url("/escalated/n/u/{$delivery->tracking_token}");
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        return new Headers(text: [
            'List-Unsubscribe' => "<{$unsub}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'X-Escalated-Newsletter-Id' => (string) $delivery->newsletter_id,
            'Message-ID' => '<n-'.$delivery->newsletter_id.'-'.$delivery->tracking_token.'@'.$host.'>',
        ]);
    }

    public function build(): self
    {
        $html = app(NewsletterRendererService::class)->render($this->delivery);

        return $this->to($this->delivery->email_at_send)->html($html);
    }

    private function resolveSubject(): string
    {
        $n = $this->delivery->newsletter;
        $subject = $n->subject ?: ($n->template?->subject_template ?? '');

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) {
            $contact = $this->delivery->contact;

            return match (trim($m[1])) {
                'contact.name' => (string) ($contact->name ?? ''),
                'contact.first_name' => explode(' ', trim((string) ($contact->name ?? '')))[0] ?? '',
                'contact.email' => (string) $contact->email,
                default => str_starts_with($m[1], 'contact.metadata.')
                    ? (string) data_get($contact->metadata ?? [], substr($m[1], strlen('contact.metadata.')), '')
                    : '',
            };
        }, $subject);
    }
}
