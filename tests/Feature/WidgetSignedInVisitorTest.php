<?php

use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Event;

/** A host user whose email address the host has not verified yet. */
class WidgetUnverifiedUser extends TestUser implements MustVerifyEmail
{
    public function hasVerifiedEmail()
    {
        return false;
    }

    public function markEmailAsVerified()
    {
        return false;
    }

    public function sendEmailVerificationNotification() {}

    public function getEmailForVerification()
    {
        return $this->email;
    }
}

/*
 * Issue #319: escalated.widget.trust_signed_in_visitors lets a visitor who is
 * signed in to the host app use the same-origin widget without an emailed
 * code. Off by default.
 */

beforeEach(function () {
    Event::fake([ChatStarted::class, ChatAssigned::class]);
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('chat_enabled', '1');
    config(['escalated.widget.allowed_origins' => ['https://shop.example.com']]);
    $this->visitor = $this->createTestUser(['name' => 'Vera Visitor', 'email' => 'vera@example.com']);
});

function signedInTicket(array $overrides = []): array
{
    return [
        'name' => 'Someone Else',
        'email' => 'someone-else@example.com',
        'subject' => 'Order question',
        'description' => 'Where is my order?',
        ...$overrides,
    ];
}

it('still asks a signed-in visitor for a code by default', function () {
    $this->actingAs($this->visitor)
        ->getJson(route('escalated.widget.config'))
        ->assertJson(['guest_verification_required' => true]);

    $this->actingAs($this->visitor)
        ->postJson(route('escalated.widget.tickets.store'), signedInTicket())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['verification_id', 'verification_code']);

    expect(Ticket::count())->toBe(0);
});

describe('with trust_signed_in_visitors on', function () {
    beforeEach(function () {
        config(['escalated.widget.trust_signed_in_visitors' => true]);
    });

    it('tells the widget to skip the code for a signed-in visitor only', function () {
        $this->getJson(route('escalated.widget.config'))
            ->assertJson(['guest_verification_required' => true]);

        $this->actingAs($this->visitor)
            ->getJson(route('escalated.widget.config'))
            ->assertJson(['guest_verification_required' => false]);
    });

    it('opens the ticket as the signed-in account and links it to them', function () {
        $this->actingAs($this->visitor)
            ->postJson(route('escalated.widget.tickets.store'), signedInTicket())
            ->assertCreated()
            ->assertJsonStructure(['reference', 'guest_access_token']);

        $ticket = Ticket::sole();
        expect($ticket->guest_email)->toBe('vera@example.com')
            ->and($ticket->guest_name)->toBe('Vera Visitor')
            ->and($ticket->requester_type)->toBe($this->visitor->getMorphClass())
            ->and((string) $ticket->requester_id)->toBe((string) $this->visitor->getKey())
            ->and(Ticket::query()->where('requester_type', $this->visitor->getMorphClass())
                ->where('requester_id', $this->visitor->getKey())->count())->toBe(1);
    });

    it('still asks a visitor who is not signed in for a code', function () {
        $this->postJson(route('escalated.widget.tickets.store'), signedInTicket())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_id', 'verification_code']);
    });

    it('never trusts the session on a request from an embedding site', function () {
        $headers = ['Origin' => 'https://shop.example.com'];

        $this->actingAs($this->visitor)
            ->getJson(route('escalated.widget.config'), $headers)
            ->assertJson(['guest_verification_required' => true]);

        $this->actingAs($this->visitor)
            ->postJson(route('escalated.widget.tickets.store'), signedInTicket(), $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_code']);

        expect(Ticket::count())->toBe(0);
    });

    it('asks for a code when the account email is not verified', function () {
        config(['escalated.user_model' => WidgetUnverifiedUser::class]);
        $unverified = WidgetUnverifiedUser::create([
            'name' => 'New Person', 'email' => 'new@example.com', 'password' => bcrypt('x'),
        ]);

        $this->actingAs($unverified)
            ->postJson(route('escalated.widget.tickets.store'), signedInTicket())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_code']);
    });

    it('starts a chat as the signed-in account without a code', function () {
        $this->actingAs($this->visitor)
            ->postJson(route('escalated.widget.chat.start'), [
                'name' => 'Someone Else', 'email' => 'someone-else@example.com', 'message' => 'Hello',
            ])
            ->assertCreated()
            ->assertJsonStructure(['session_id', 'ticket_reference']);

        $ticket = Ticket::sole();
        expect($ticket->guest_email)->toBe('vera@example.com')
            ->and($ticket->guest_name)->toBe('Vera Visitor')
            // Chats are not linked to the account yet.
            ->and($ticket->requester_id)->toBeNull();
    });
});
