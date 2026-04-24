<?php

namespace Escalated\Laravel\Tests\Unit;

use Escalated\Laravel\Mail\NotificationThreading;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\TestCase;
use Symfony\Component\Mime\Email;

/**
 * Tests for NotificationThreading — verifies the headers every
 * outbound notification gets when its withSymfonyMessage callback
 * delegates to this helper.
 */
class NotificationThreadingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('escalated.email.domain', 'support.example.com');
        config()->set('escalated.email.inbound_secret', 'test-secret-for-hmac');
    }

    private function ticket(int $id = 42): Ticket
    {
        $ticket = new Ticket;
        $ticket->id = $id;

        return $ticket;
    }

    public function test_apply_anchor_sets_ticket_root_message_id(): void
    {
        $message = new Email;
        NotificationThreading::applyAnchor($message, $this->ticket());

        $this->assertSame(
            'ticket-42@support.example.com',
            trim((string) $message->getHeaders()->get('Message-ID')->getId(), '<>')
        );
        // Anchor message has no In-Reply-To (it IS the anchor).
        $this->assertNull($message->getHeaders()->get('In-Reply-To'));
    }

    public function test_apply_anchor_sets_signed_reply_to(): void
    {
        $message = new Email;
        NotificationThreading::applyAnchor($message, $this->ticket());

        $replyTo = $message->getReplyTo();
        $this->assertCount(1, $replyTo);
        $this->assertMatchesRegularExpression(
            '/^reply\+42\.[a-f0-9]{8}@support\.example\.com$/',
            $replyTo[0]->getAddress()
        );
    }

    public function test_apply_thread_sets_in_reply_to_and_references(): void
    {
        $message = new Email;
        NotificationThreading::applyThread($message, $this->ticket());

        $this->assertSame(
            'ticket-42@support.example.com',
            trim((string) $message->getHeaders()->get('In-Reply-To')->getId(), '<>')
        );
        $this->assertSame(
            'ticket-42@support.example.com',
            trim((string) $message->getHeaders()->get('References')->getId(), '<>')
        );
    }

    public function test_apply_thread_with_reply_id_sets_own_message_id(): void
    {
        $message = new Email;
        NotificationThreading::applyThread($message, $this->ticket(), 7);

        $this->assertSame(
            'ticket-42-reply-7@support.example.com',
            trim((string) $message->getHeaders()->get('Message-ID')->getId(), '<>')
        );
        $this->assertSame(
            'ticket-42@support.example.com',
            trim((string) $message->getHeaders()->get('In-Reply-To')->getId(), '<>')
        );
    }

    public function test_inbound_secret_blank_skips_reply_to(): void
    {
        config()->set('escalated.email.inbound_secret', '');

        $message = new Email;
        NotificationThreading::applyAnchor($message, $this->ticket());

        $this->assertCount(0, $message->getReplyTo());
    }

    public function test_domain_falls_back_to_app_url_host(): void
    {
        config()->set('escalated.email.domain', '');
        config()->set('app.url', 'https://helpdesk.mycompany.test');

        $message = new Email;
        NotificationThreading::applyAnchor($message, $this->ticket());

        $this->assertStringEndsWith(
            '@helpdesk.mycompany.test',
            trim((string) $message->getHeaders()->get('Message-ID')->getId(), '<>')
        );
    }
}
