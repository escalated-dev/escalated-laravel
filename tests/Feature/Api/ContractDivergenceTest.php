<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Resources\TicketCollectionResource;
use Escalated\Laravel\Http\Resources\TicketResource;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function divAgent(array $abilities = ['agent', 'admin'], array $attrs = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'Div Agent',
        'email' => 'div-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
        'is_admin' => true,
    ], $attrs));
    $result = ApiToken::createToken($user, 'Div Token', $abilities);

    return ['user' => $user, 'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']]];
}

function divTicket(TestUser $requester, array $attrs = []): Ticket
{
    static $n = 0;
    $n++;

    return Ticket::create(array_merge([
        'reference' => 'ESC-'.str_pad((string) (90000 + $n), 5, '0', STR_PAD_LEFT),
        'requester_type' => $requester->getMorphClass(),
        'requester_id' => $requester->getKey(),
        'subject' => 'Ticket '.$n,
        'description' => 'Body.',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Medium,
    ], $attrs));
}

it('always includes last_reply_at, sla_breached, is_following and followers_count on ticket show', function () {
    $api = divAgent();
    $ticket = divTicket($api['user']);

    $data = $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.tickets.show', $ticket->reference))
        ->assertOk()->json('data');

    expect($data)->toHaveKeys(['last_reply_at', 'sla_breached', 'is_following', 'followers_count']);
    expect($data['last_reply_at'])->toBeNull();
    expect($data['sla_breached'])->toBeFalse();
    expect($data['is_following'])->toBeFalse();
    expect($data['followers_count'])->toBe(0);

    $ticket->follow($api['user']->getKey());
    Reply::create([
        'ticket_id' => $ticket->id,
        'author_type' => $api['user']->getMorphClass(),
        'author_id' => $api['user']->getKey(),
        'body' => 'Hello',
        'is_internal_note' => false,
    ]);

    $data = $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.tickets.show', $ticket->reference))
        ->json('data');

    expect($data['is_following'])->toBeTrue();
    expect($data['followers_count'])->toBe(1);
    expect($data['last_reply_at'])->toBeString();
});

it('emits ticket_type question when the stored value is null', function () {
    $api = divAgent();
    $ticket = divTicket($api['user']);
    $request = Request::create('/');

    // The column is NOT NULL on a fresh install; installs that added it as
    // nullable (accounts migration) can hold null.
    $ticket->setAttribute('ticket_type', null);

    expect((new TicketResource($ticket))->toArray($request)['ticket_type'])->toBe('question');
    expect((new TicketCollectionResource($ticket))->toArray($request)['ticket_type'])->toBe('question');

    $ticket->setAttribute('ticket_type', 'incident');
    expect((new TicketResource($ticket))->toArray($request)['ticket_type'])->toBe('incident');
});

it('returns actions on macros and keeps steps', function () {
    $api = divAgent();
    Macro::create([
        'name' => 'Close as solved',
        'actions' => [['type' => 'status', 'value' => 'resolved']],
        'is_shared' => true,
        'order' => 0,
    ]);

    $item = $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.macros'))
        ->assertOk()->json('data.0');

    expect($item['actions'])->toBe([['type' => 'status', 'value' => 'resolved']]);
    expect($item)->toHaveKey('steps');
});

it('answers 404 with a message for an unknown report export type', function () {
    $api = divAgent();

    $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.reports.export', 'nonsense'))
        ->assertNotFound()
        ->assertJsonStructure(['message']);

    $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.reports.export', ['type' => 'tickets', 'format' => 'csv']))
        ->assertOk();
});

it('following=1 returns only tickets the caller follows', function () {
    $api = divAgent();
    $other = divAgent(['agent'], ['is_admin' => false]);
    $mine = divTicket($api['user']);
    $theirs = divTicket($api['user']);
    divTicket($api['user']);
    $mine->follow($api['user']->getKey());
    $theirs->follow($other['user']->getKey());

    $refs = collect($this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.tickets.index', ['following' => 1]))
        ->assertOk()->json('data'))->pluck('reference')->all();

    expect($refs)->toBe([$mine->reference]);
});

it('casts report summary by_status and by_priority values to integers', function () {
    $api = divAgent();
    divTicket($api['user']);
    divTicket($api['user'], ['status' => TicketStatus::Resolved]);

    $json = $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.reports.summary'))
        ->assertOk()->json();

    expect($json['by_status'])->not->toBeEmpty();
    foreach (array_merge($json['by_status'], $json['by_priority']) as $row) {
        expect($row['value'])->toBeInt();
    }
});

it('answers 422 with errors.status for a refused status transition', function (string $from, string $to) {
    $api = divAgent();
    $ticket = divTicket($api['user'], ['status' => $from]);

    $this->withHeaders($api['headers'])
        ->patchJson(route('escalated.api.tickets.status', $ticket->reference), ['status' => $to])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['status']]);

    expect($ticket->fresh()->status->value)->toBe($from);
})->with([
    'closed to waiting_on_customer' => ['closed', 'waiting_on_customer'],
    'open to reopened' => ['open', 'reopened'],
    'resolved to escalated' => ['resolved', 'escalated'],
]);

it('treats setting the current status as a 200 no-op', function (string $status) {
    $api = divAgent();
    $ticket = divTicket($api['user'], ['status' => $status]);
    $updatedAt = $ticket->fresh()->updated_at;

    $this->withHeaders($api['headers'])
        ->patchJson(route('escalated.api.tickets.status', $ticket->reference), ['status' => $status])
        ->assertOk()
        ->assertJson(['message' => 'Status updated.', 'status' => $status]);

    expect($ticket->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
})->with(['open', 'closed', 'resolved', 'reopened', 'escalated']);

it('still performs an allowed status transition', function () {
    $api = divAgent();
    $ticket = divTicket($api['user']);

    $this->withHeaders($api['headers'])
        ->patchJson(route('escalated.api.tickets.status', $ticket->reference), ['status' => 'resolved'])
        ->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('breaks created_at ties by id in the sort direction', function (string $dir) {
    $api = divAgent();
    $at = now()->subHour()->startOfSecond();
    $ids = [];
    for ($i = 0; $i < 6; $i++) {
        $ids[] = divTicket($api['user'], ['created_at' => $at, 'updated_at' => $at])->id;
    }
    sort($ids);
    $expected = $dir === 'asc' ? $ids : array_reverse($ids);

    $seen = [];
    foreach ([1, 2, 3] as $page) {
        $res = $this->withHeaders($api['headers'])
            ->getJson(route('escalated.api.tickets.index', ['per_page' => 2, 'page' => $page, 'sort_by' => 'created_at', 'sort_dir' => $dir]))
            ->assertOk()->json('data');
        $seen = array_merge($seen, array_column($res, 'id'));
    }

    expect($seen)->toBe($expected);
})->with(['asc', 'desc']);
