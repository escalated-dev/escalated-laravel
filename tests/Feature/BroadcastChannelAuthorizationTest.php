<?php

use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\UlidTestUser;
use Escalated\Laravel\Tests\TestDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

// ULIDs issued today all begin with "01", and an (int) cast stops at the first
// letter, so every one of them casts to 1.
const CHANNEL_TEST_ULID_A = '01J8Z3K4M5N6P7Q8R9S0T1V2W3';
const CHANNEL_TEST_ULID_B = '01J8Z3K4M5N6P7Q8R9S0T1V2W4';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    // The provider loads these only when escalated.broadcasting.enabled is
    // true at boot.
    require __DIR__.'/../../routes/channels.php';
});

function escalatedChannel(string $name): Closure
{
    return Broadcast::driver()->getChannels()[$name];
}

function ulidChannelUser(string $id): UlidTestUser
{
    return (new UlidTestUser)->forceFill([
        'id' => $id,
        'name' => 'ULID user',
        'is_agent' => false,
        'is_admin' => false,
    ]);
}

// The fixture users table has integer ids, so requester_id and agent_id are
// integer columns and MySQL and PostgreSQL refuse a ULID in them. SQLite keeps
// the text as written, which is all these cases need.
$integerUserColumns = fn () => TestDatabase::driver() !== 'sqlite';
$integerUserColumnsReason = 'user id columns are integers on this driver';

it('lets a requester join their own ticket channel', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
    ]);

    expect(escalatedChannel('escalated.tickets.{ticketId}')($user, (string) $ticket->id))->toBeTrue();
});

it('does not let a user join a ticket raised by another kind of requester with the same id', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => Contact::class,
        'requester_id' => $user->getKey(),
    ]);

    expect(escalatedChannel('escalated.tickets.{ticketId}')($user, (string) $ticket->id))->toBeFalse();
});

it('does not let one ULID user join another ULID user\'s ticket channel', function () {
    $owner = ulidChannelUser(CHANNEL_TEST_ULID_B);
    $ticket = Ticket::factory()->create([
        'requester_type' => $owner->getMorphClass(),
        'requester_id' => $owner->getKey(),
    ]);
    $join = escalatedChannel('escalated.tickets.{ticketId}');

    expect($join(ulidChannelUser(CHANNEL_TEST_ULID_A), (string) $ticket->id))->toBeFalse()
        ->and($join($owner, (string) $ticket->id))->toBeTrue();
})->skip($integerUserColumns, $integerUserColumnsReason);

it('lets an agent join only their own agent channel', function () {
    $join = escalatedChannel('escalated.agents.{agentId}');
    $ulidAgent = ulidChannelUser(CHANNEL_TEST_ULID_A);
    $agent = $this->createAgent();

    expect($join($ulidAgent, CHANNEL_TEST_ULID_B))->toBeFalse()
        ->and($join($ulidAgent, CHANNEL_TEST_ULID_A))->toBeTrue()
        ->and($join($agent, $agent->getKey().'abc'))->toBeFalse()
        ->and($join($agent, (string) $agent->getKey()))->toBeTrue();
});

it('lets the assigned agent join a chat session channel', function () {
    $agent = $this->createTestUser();
    $session = ChatSession::factory()->create(['agent_id' => $agent->getKey()]);

    expect(escalatedChannel('escalated.chat.{sessionId}')($agent, (string) $session->id))->toBeTrue();
});

it('does not treat a ULID user as the assigned agent of another agent\'s chat', function () {
    $session = ChatSession::factory()->create(['agent_id' => CHANNEL_TEST_ULID_B]);
    $join = escalatedChannel('escalated.chat.{sessionId}');

    expect($join(ulidChannelUser(CHANNEL_TEST_ULID_A), (string) $session->id))->toBeFalse()
        ->and($join(ulidChannelUser(CHANNEL_TEST_ULID_B), (string) $session->id))->toBeTrue();
})->skip($integerUserColumns, $integerUserColumnsReason);
