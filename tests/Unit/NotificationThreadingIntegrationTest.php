<?php

namespace Escalated\Laravel\Tests\Unit;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\NewTicketNotification;
use Escalated\Laravel\Notifications\SlaBreachNotification;
use Escalated\Laravel\Notifications\TicketAssignedNotification;
use Escalated\Laravel\Notifications\TicketEscalatedNotification;
use Escalated\Laravel\Notifications\TicketReplyNotification;
use Escalated\Laravel\Notifications\TicketResolvedNotification;
use Escalated\Laravel\Notifications\TicketStatusChangedNotification;
use Escalated\Laravel\Tests\TestCase;
use Symfony\Component\Mime\Email;

/**
 * End-to-end tests confirming every notification's
 * withSymfonyMessage callback writes the canonical Message-ID /
 * In-Reply-To / References / Reply-To headers onto the underlying
 * Symfony Email.
 *
 * Complements NotificationThreadingTest (which exercises the helper
 * in isolation) by running each notification's closure against a
 * real Email and asserting the final header output.
 */
class NotificationThreadingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('escalated.email.domain', 'support.example.com');
        config()->set('escalated.email.inbound_secret', 'test-secret');
    }

    private function runCallbacksOn(Email $email, $mail): void
    {
        foreach ($mail->callbacks as $cb) {
            $cb($email);
        }
    }

    public function test_new_ticket_notification_applies_anchor_headers(): void
    {
        $ticket = Ticket::factory()->create();
        $user = $this->createTestUser();
        $mail = (new NewTicketNotification($ticket))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertSame(
            "ticket-{$ticket->id}@support.example.com",
            trim((string) $email->getHeaders()->get('Message-ID')->getId(), '<>')
        );
        $this->assertNull($email->getHeaders()->get('In-Reply-To'));
        $this->assertMatchesRegularExpression(
            "/^reply\\+{$ticket->id}\\.[a-f0-9]{8}@support\\.example\\.com$/",
            $email->getReplyTo()[0]->getAddress()
        );
    }

    public function test_ticket_reply_notification_sets_reply_message_id_and_thread_headers(): void
    {
        $ticket = Ticket::factory()->create();
        $reply = Reply::factory()->create(['ticket_id' => $ticket->id]);
        $user = $this->createTestUser();
        $mail = (new TicketReplyNotification($reply))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertSame(
            "ticket-{$ticket->id}-reply-{$reply->id}@support.example.com",
            trim((string) $email->getHeaders()->get('Message-ID')->getId(), '<>')
        );
        $this->assertSame(
            "ticket-{$ticket->id}@support.example.com",
            trim((string) $email->getHeaders()->get('In-Reply-To')->getId(), '<>')
        );
        $this->assertSame(
            "ticket-{$ticket->id}@support.example.com",
            trim((string) $email->getHeaders()->get('References')->getId(), '<>')
        );
    }

    public function test_sla_breach_notification_joins_ticket_thread(): void
    {
        $ticket = Ticket::factory()->create();
        $user = $this->createTestUser();
        $mail = (new SlaBreachNotification($ticket, 'first_response'))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertSame(
            "ticket-{$ticket->id}@support.example.com",
            trim((string) $email->getHeaders()->get('In-Reply-To')->getId(), '<>')
        );
        $this->assertSame(
            "ticket-{$ticket->id}@support.example.com",
            trim((string) $email->getHeaders()->get('References')->getId(), '<>')
        );
    }

    public function test_ticket_assigned_notification_joins_ticket_thread(): void
    {
        $ticket = Ticket::factory()->create();
        $user = $this->createTestUser();
        $mail = (new TicketAssignedNotification($ticket))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertNotNull($email->getHeaders()->get('In-Reply-To'));
    }

    public function test_ticket_escalated_notification_joins_ticket_thread(): void
    {
        $ticket = Ticket::factory()->create();
        $user = $this->createTestUser();
        $mail = (new TicketEscalatedNotification($ticket))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertNotNull($email->getHeaders()->get('In-Reply-To'));
    }

    public function test_ticket_resolved_notification_joins_ticket_thread(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Resolved]);
        $user = $this->createTestUser();
        $mail = (new TicketResolvedNotification($ticket))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertNotNull($email->getHeaders()->get('In-Reply-To'));
    }

    public function test_ticket_status_changed_notification_joins_ticket_thread(): void
    {
        $ticket = Ticket::factory()->create();
        $user = $this->createTestUser();
        $mail = (new TicketStatusChangedNotification(
            $ticket,
            TicketStatus::Open,
            TicketStatus::InProgress
        ))->toMail($user);
        $email = new Email;

        $this->runCallbacksOn($email, $mail);

        $this->assertNotNull($email->getHeaders()->get('In-Reply-To'));
    }

    public function test_every_notification_applies_signed_reply_to(): void
    {
        $ticket = Ticket::factory()->create();
        $reply = Reply::factory()->create(['ticket_id' => $ticket->id]);
        $user = $this->createTestUser();

        $cases = [
            new NewTicketNotification($ticket),
            new TicketReplyNotification($reply),
            new SlaBreachNotification($ticket, 'resolution'),
            new TicketAssignedNotification($ticket),
            new TicketEscalatedNotification($ticket),
            new TicketResolvedNotification($ticket),
            new TicketStatusChangedNotification($ticket, TicketStatus::Open, TicketStatus::Resolved),
        ];

        foreach ($cases as $notification) {
            $email = new Email;
            $mail = $notification->toMail($user);
            $this->runCallbacksOn($email, $mail);

            $this->assertMatchesRegularExpression(
                "/^reply\\+{$ticket->id}\\.[a-f0-9]{8}@support\\.example\\.com$/",
                $email->getReplyTo()[0]->getAddress(),
                'Notification '.get_class($notification).' did not apply signed Reply-To'
            );
        }
    }
}
