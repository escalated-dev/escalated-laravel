<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function openFilterSetup(): array
{
    $user = TestUser::create([
        'name' => 'Open Agent',
        'email' => 'open-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);
    $result = ApiToken::createToken($user, 'Open Token', ['agent']);

    $byStatus = [];
    foreach (TicketStatus::cases() as $status) {
        $byStatus[$status->value] = Ticket::create([
            'reference' => 'ESC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'requester_type' => $user->getMorphClass(),
            'requester_id' => $user->getKey(),
            'subject' => 'Ticket '.$status->value,
            'description' => 'Body.',
            'status' => $status,
            'priority' => TicketPriority::Medium,
        ]);
    }

    return ['headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']], 'tickets' => $byStatus];
}

it('open=1 returns every status except resolved and closed', function () {
    ['headers' => $headers, 'tickets' => $tickets] = openFilterSetup();

    $statuses = collect($this->getJson('/support/api/v1/tickets?open=1&per_page=100', $headers)
        ->assertOk()->json('data'))->pluck('status')->all();

    $expected = collect(TicketStatus::cases())
        ->reject(fn ($s) => in_array($s, [TicketStatus::Resolved, TicketStatus::Closed]))
        ->map->value->sort()->values()->all();
    sort($statuses);

    expect($statuses)->not->toContain('resolved', 'closed')
        ->and($statuses)->toBe($expected)
        ->and($statuses)->toContain('in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'reopened');
});

it('open=0 and no flag return every status', function () {
    ['headers' => $headers] = openFilterSetup();

    $count = count(TicketStatus::cases());
    expect($this->getJson('/support/api/v1/tickets?open=0&per_page=100', $headers)->assertOk()->json('data'))->toHaveCount($count)
        ->and($this->getJson('/support/api/v1/tickets?per_page=100', $headers)->assertOk()->json('data'))->toHaveCount($count);
});

it('open combines with other filters', function () {
    ['headers' => $headers] = openFilterSetup();

    $this->getJson('/support/api/v1/tickets?open=1&status=resolved', $headers)
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/support/api/v1/tickets?open=1&status=escalated', $headers)
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'escalated');
});

it('rejects a non boolean open value', function () {
    ['headers' => $headers] = openFilterSetup();

    $this->getJson('/support/api/v1/tickets?open=maybe', $headers)
        ->assertStatus(422)->assertJsonValidationErrors('open');
});
