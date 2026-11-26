<?php

/*
 * Who wrote a chat line is decided by identity, not by account type: in many
 * host apps staff and customers are the same user model. A guest's lines on a
 * ticket that has a requester (guest_policy_mode = guest_user) are written as
 * the requester's, so the reply listeners treat them as the customer's own.
 */

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatMessage;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\TicketReplyNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    EscalatedSettings::set('chat_enabled', '1');
});

function chatWithRequester($requester, array $ticket = []): array
{
    $ticket = Ticket::factory()->create(array_merge([
        'status' => TicketStatus::Live,
        'channel' => 'chat',
        'requester_type' => $requester->getMorphClass(),
        'requester_id' => $requester->getKey(),
    ], $ticket));
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    return [$ticket, $session];
}

it('tells agent and customer lines apart when both are the same user model', function () {
    Event::fake([ChatMessage::class]);
    $customer = $this->createTestUser(['email' => 'customer@example.com', 'name' => 'Casey Customer']);
    $agent = $this->createAgent(['name' => 'Agent Ada']);
    [$ticket] = chatWithRequester($customer);

    Reply::create(['ticket_id' => $ticket->id, 'body' => 'From the customer', 'author_type' => $customer->getMorphClass(), 'author_id' => $customer->id]);
    Reply::create(['ticket_id' => $ticket->id, 'body' => 'From the agent', 'author_type' => $agent->getMorphClass(), 'author_id' => $agent->id]);

    $messages = collect($ticket->fresh()->chat_messages)->keyBy('body');

    expect($messages['From the customer']['is_agent'])->toBeFalse()
        ->and($messages['From the agent']['is_agent'])->toBeTrue();
});

it('shows the visitor their own authored lines as theirs in the widget', function () {
    Event::fake([ChatMessage::class]);
    $customer = $this->createTestUser(['email' => 'customer@example.com']);
    $agent = $this->createAgent(['name' => 'Agent Ada']);
    [$ticket, $session] = chatWithRequester($customer, ['guest_name' => 'Casey']);
    $token = $this->guestToken($ticket, 'chat');
    $session->update(['customer_session_id' => hash('sha256', $token)]);

    Reply::create(['ticket_id' => $ticket->id, 'body' => 'From the customer', 'author_type' => $customer->getMorphClass(), 'author_id' => $customer->id]);
    Reply::create(['ticket_id' => $ticket->id, 'body' => 'From the agent', 'author_type' => $agent->getMorphClass(), 'author_id' => $agent->id]);

    $messages = collect($this->getJson("/support/widget/chat/{$token}/messages")->assertOk()->json('messages'))->keyBy('body');

    expect($messages['From the customer']['is_agent'])->toBeFalse()
        ->and($messages['From the customer']['author']['name'])->toBe('Casey')
        ->and($messages['From the agent']['is_agent'])->toBeTrue()
        ->and($messages['From the agent']['author']['name'])->toBe('Agent Ada');
});

it('writes a visitor\'s message as the requester\'s on a ticket that has one', function () {
    Event::fake([ChatMessage::class]);
    Notification::fake();
    $guestUser = $this->createTestUser(['email' => 'guest-user@example.com', 'name' => 'Guest account']);
    [$ticket, $session] = chatWithRequester($guestUser, ['guest_name' => 'Vera', 'first_response_at' => null]);
    $token = $this->guestToken($ticket, 'chat');
    $session->update(['customer_session_id' => hash('sha256', $token)]);

    $replyId = $this->postJson("/support/widget/chat/{$token}/message", ['body' => 'Is anyone there?'])
        ->assertOk()
        ->json('reply_id');

    $reply = Reply::find($replyId);
    expect($reply->author_type)->toBe($guestUser->getMorphClass())
        ->and((string) $reply->author_id)->toBe((string) $guestUser->id)
        // The visitor's own message is not a first response, and the
        // requester is not notified about it.
        ->and($ticket->fresh()->first_response_at)->toBeNull();
    Notification::assertNotSentTo($guestUser, TicketReplyNotification::class);
    Event::assertDispatched(ChatMessage::class, fn (ChatMessage $event) => ! $event->isAgent
        && $event->broadcastWith()['author'] === 'Vera');
});

it('leaves a visitor\'s message without an author on a ticket without a requester', function () {
    Event::fake([ChatMessage::class]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat', 'requester_type' => null, 'requester_id' => null]);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);
    $token = $this->guestToken($ticket, 'chat');
    $session->update(['customer_session_id' => hash('sha256', $token)]);

    $replyId = $this->postJson("/support/widget/chat/{$token}/message", ['body' => 'Hello'])->assertOk()->json('reply_id');

    expect(Reply::find($replyId)->author_type)->toBeNull();
});

it('records the opening message as the requester\'s under guest_policy_mode guest_user', function () {
    Event::fake([ChatStarted::class, ChatAssigned::class]);
    EscalatedSettings::set('guest_policy_mode', 'guest_user');
    $guestUser = $this->createTestUser(['email' => 'guest-user@example.com']);
    EscalatedSettings::set('guest_policy_user_id', (string) $guestUser->id);

    $start = $this->postJson('/support/widget/chat/start', [
        ...$this->guestProof('vera@example.com', 'chat'),
        'name' => 'Vera',
        'email' => 'vera@example.com',
        'message' => 'My parcel is late',
    ])->assertCreated();

    $ticket = Ticket::where('reference', $start->json('ticket_reference'))->first();
    $opening = $ticket->replies()->first();
    expect((string) $ticket->requester_id)->toBe((string) $guestUser->id)
        ->and((string) $opening->author_id)->toBe((string) $guestUser->id);

    $messages = $this->getJson('/support/widget/chat/'.$start->json('session_id').'/messages')->json('messages');
    expect($messages[0]['is_agent'])->toBeFalse()
        ->and($messages[0]['author']['name'])->toBe('Vera')
        ->and($ticket->chat_messages[0]['is_agent'])->toBeFalse();
});
