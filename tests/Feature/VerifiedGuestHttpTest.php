<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Mail\GuestVerificationCode;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\GuestVerification;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentService;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Services\GuestTicketService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('widget_enabled', '1');
    Mail::fake();
});

it('requires new mailbox proof before creating any guest identity or ticket', function (string $path) {
    Event::fake([TicketCreated::class]);
    $this->postJson($path, [
        'name' => 'Guest', 'email' => 'guest@example.com', 'guest_name' => 'Guest', 'guest_email' => 'guest@example.com',
        'subject' => 'Help', 'description' => 'Details',
    ])->assertUnprocessable()->assertJsonValidationErrors(['verification_id', 'verification_code']);
    expect(Ticket::count())->toBe(0);
    expect(Contact::count())->toBe(0);
    Event::assertNotDispatched(TicketCreated::class);
})->with(['/support/guest', '/support/widget/tickets', '/support/api/v1/mobile/guest/tickets']);

it('verifies email and creates a mobile ticket whose grant works across web and widget', function () {
    $challenge = $this->postJson('/support/api/v1/mobile/guest/verification', ['email' => 'guest@example.com', 'purpose' => 'ticket'])
        ->assertAccepted()->assertHeader('Referrer-Policy', 'no-referrer')->json();
    expect($challenge)->not->toHaveKey('code');
    $code = Mail::sent(GuestVerificationCode::class)->first()->code;
    $payload = ['name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'description' => 'Details',
        'verification_id' => $challenge['verification_id'], 'verification_code' => $code];
    $created = $this->postJson('/support/api/v1/mobile/guest/tickets', $payload)->assertCreated()->json('data');
    $token = $created['guest_access_token'];
    expect($created['guest_access_expires_at'])->not->toBeNull();
    $this->get('/support/guest/'.$token)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    $this->getJson('/support/api/v1/mobile/guest/tickets/'.$token)->assertOk()->assertJsonPath('data.subject', 'Help');
    $this->withToken($token)->getJson('/support/widget/tickets/'.$created['reference'])->assertOk()->assertJsonPath('subject', 'Help');
    $this->postJson('/support/api/v1/mobile/guest/tickets', $payload)->assertUnprocessable();
    $this->postJson('/support/api/v1/mobile/guest/tickets/'.$token.'/replies', ['email' => 'GUEST@example.com', 'body' => 'Thank you'])->assertCreated();
    expect(Ticket::count())->toBe(1);
    $this->travel(25)->hours();
    $this->getJson('/support/api/v1/mobile/guest/tickets/'.$token)->assertNotFound();
});

it('never grants widget access from just a reference and matching email', function () {
    $ticket = Ticket::factory()->create(['guest_email' => 'guest@example.com']);
    $this->getJson('/support/widget/tickets/'.$ticket->reference.'?email=guest@example.com')->assertNotFound();
    $other = Ticket::factory()->create(['guest_email' => 'guest@example.com']);
    $token = $this->guestToken($other);
    $this->withToken($token)->getJson('/support/widget/tickets/'.$ticket->reference)->assertNotFound();
});

it('looks up host-supplied tracking references only after fresh mailbox proof', function () {
    $mine = Ticket::factory()->create(['guest_email' => 'guest@example.com', 'external_reference' => 'TRACK-123']);
    Ticket::factory()->create(['guest_email' => 'someone-else@example.com', 'external_reference' => 'TRACK-123']);
    $proof = $this->guestProof('guest@example.com', 'lookup');
    $found = $this->postJson('/support/widget/lookup', $proof + ['email' => 'guest@example.com', 'reference' => 'TRACK-123'])
        ->assertOk()->assertJsonCount(1, 'data')->json('data.0');
    expect($found['reference'])->toBe($mine->reference);
    expect(app(GuestAccess::class)->resolve($found['guest_access_token'])->id)->toBe($mine->id);
    $this->postJson('/support/widget/lookup', $proof + ['email' => 'guest@example.com', 'reference' => 'TRACK-123'])->assertUnprocessable();
});

it('does not let anonymous ticket creation assign an external reference', function () {
    $proof = $this->guestProof('guest@example.com');
    $this->postJson('/support/widget/tickets', $proof + ['name' => 'Guest', 'email' => 'guest@example.com',
        'subject' => 'Help', 'description' => 'Details', 'external_reference' => 'NOT-MINE'])->assertCreated();
    expect(Ticket::first()->external_reference)->toBeNull();
});

it('requires fresh proof for chat and protects polling and replies with an expiring chat grant', function () {
    EscalatedSettings::set('chat_enabled', '1');
    $body = ['name' => 'Guest', 'email' => 'guest@example.com', 'message' => 'Hello'];
    $this->postJson('/support/widget/chat/start', $body)->assertUnprocessable();
    $proof = $this->guestProof('guest@example.com', 'chat');
    $created = $this->postJson('/support/widget/chat/start', $proof + $body)->assertCreated()->json();
    $ticket = app(GuestAccess::class)->resolve($created['session_id'], 'chat');
    Reply::factory()->create(['ticket_id' => $ticket->id, 'body' => 'Public message', 'is_internal_note' => false, 'author_type' => null, 'author_id' => null]);
    Reply::factory()->create(['ticket_id' => $ticket->id, 'body' => 'Private note', 'is_internal_note' => true, 'author_type' => null, 'author_id' => null]);
    $path = '/support/widget/chat/'.$created['session_id'];
    $this->getJson($path.'/messages')->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Public message');
    $this->postJson($path.'/messages', ['body' => 'Customer reply'])->assertOk();
    $this->getJson('/support/api/v1/mobile/guest/tickets/'.$created['session_id'])->assertNotFound();
    $session = ChatSession::where('ticket_id', $ticket->id)->first();
    $this->postJson('/support/widget/chat/'.$session->customer_session_id.'/message', ['body' => 'Wrong token'])->assertNotFound();
    $this->travel(25)->hours();
    $this->getJson($path.'/messages')->assertNotFound();
});

it('does not dispatch ticket creation before the complete verified aggregate commits', function () {
    Event::fake([TicketCreated::class]);
    Escalated::db()->beginTransaction();
    $proof = $this->guestProof('guest@example.com');
    $this->postJson('/support/widget/tickets', $proof + ['name' => 'Guest', 'email' => 'guest@example.com',
        'subject' => 'Verified', 'description' => 'Details'])->assertCreated();
    Event::assertNotDispatched(TicketCreated::class);
    Escalated::db()->commit();
    Event::assertDispatched(TicketCreated::class, fn ($event) => $event->ticket->guest_email_verified_at !== null && $event->ticket->contact_id !== null && $event->ticket->guest_access_hash !== null);
});

it('rolls back the ticket, contact and proof and removes files when a later upload fails', function () {
    Storage::fake('local');
    Event::fake([TicketCreated::class]);
    $real = new AttachmentService;
    $calls = 0;
    $this->mock(AttachmentService::class)->shouldReceive('store')->twice()->andReturnUsing(function ($ticket, $file) use ($real, &$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('Upload failed');
        }

        return $real->store($ticket, $file);
    });
    $proof = $this->guestProof('guest@example.com');
    expect(fn () => app(GuestTicketService::class)->create($proof + [
        'name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'description' => 'Details',
    ], 'web', [UploadedFile::fake()->create('first.txt'), UploadedFile::fake()->create('second.txt')]))->toThrow(RuntimeException::class, 'Upload failed');
    expect(Ticket::count())->toBe(0)->and(Contact::count())->toBe(0)->and(Attachment::count())->toBe(0);
    expect(GuestVerification::find($proof['verification_id'])->used_at)->toBeNull();
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Event::assertNotDispatched(TicketCreated::class);
});

it('suppresses the creation event when an enclosing transaction rolls back', function () {
    Event::fake([TicketCreated::class]);
    $proof = $this->guestProof('guest@example.com');
    Escalated::db()->beginTransaction();
    app(GuestTicketService::class)->create($proof + [
        'name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'description' => 'Details',
    ], 'web');
    Escalated::db()->rollBack();
    expect(Ticket::count())->toBe(0)->and(Contact::count())->toBe(0);
    expect(GuestVerification::find($proof['verification_id'])->used_at)->toBeNull();
    Event::assertNotDispatched(TicketCreated::class);
});
