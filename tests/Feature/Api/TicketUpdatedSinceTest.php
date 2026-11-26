<?php

use Carbon\Carbon;
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

function sinceAgent(): array
{
    $user = TestUser::create([
        'name' => 'Since Agent',
        'email' => 'since-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);
    $result = ApiToken::createToken($user, 'Since Token', ['agent']);

    return ['user' => $user, 'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']]];
}

function sinceTicket(TestUser $requester, array $attrs = [], ?DateTimeInterface $updatedAt = null): Ticket
{
    $ticket = Ticket::create(array_merge([
        'reference' => 'ESC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'requester_type' => $requester->getMorphClass(),
        'requester_id' => $requester->getKey(),
        'subject' => 'Ticket',
        'description' => 'Body.',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Medium,
    ], $attrs));

    if ($updatedAt) {
        Ticket::withoutTimestamps(fn () => $ticket->forceFill(['updated_at' => Carbon::instance($updatedAt)->setTimezone(config('app.timezone'))])->save());
    }

    return $ticket->fresh();
}

it('filters by updated_since', function (string $timezone) {
    $previous = date_default_timezone_get();
    config()->set('app.timezone', $timezone);
    date_default_timezone_set($timezone);
    $this->beforeApplicationDestroyed(fn () => date_default_timezone_set($previous));

    $agent = sinceAgent();
    $cutoff = now()->subDays(2)->utc();
    sinceTicket($agent['user'], [], $cutoff->copy()->subHour());
    sinceTicket($agent['user'], [], $cutoff->copy()->addHour());
    sinceTicket($agent['user']);

    $zulu = $cutoff->format('Y-m-d\TH:i:s\Z');
    $offset = $cutoff->copy()->setTimezone('+02:00')->format('Y-m-d\TH:i:sP');

    $this->getJson('/support/api/v1/tickets?updated_since='.urlencode($zulu), $agent['headers'])
        ->assertOk()->assertJsonPath('meta.total', 2);

    $this->getJson('/support/api/v1/tickets?updated_since='.urlencode($offset), $agent['headers'])
        ->assertOk()->assertJsonPath('meta.total', 2);

    $this->getJson('/support/api/v1/tickets?updated_since=not-a-date', $agent['headers'])
        ->assertStatus(422)->assertJsonValidationErrors('updated_since');
})->with(['UTC', 'America/Toronto', 'Asia/Tokyo']);

it('exposes last_customer_reply_at', function () {
    $agent = sinceAgent();
    $at = now()->subHour()->startOfSecond();
    $with = sinceTicket($agent['user'], ['last_customer_activity_at' => $at]);
    $without = sinceTicket($agent['user']);
    Ticket::whereKey($without->getKey())->update(['last_customer_activity_at' => null]);

    $list = collect($this->getJson('/support/api/v1/tickets', $agent['headers'])->assertOk()->json('data'))->keyBy('reference');
    expect($list[$with->reference]['last_customer_reply_at'])->toBe($at->toIso8601String())
        ->and($list[$without->reference]['last_customer_reply_at'])->toBeNull();

    $this->getJson('/support/api/v1/tickets/'.$with->reference, $agent['headers'])
        ->assertOk()->assertJsonPath('data.last_customer_reply_at', $at->toIso8601String());
    $this->getJson('/support/api/v1/tickets/'.$without->reference, $agent['headers'])
        ->assertOk()->assertJsonPath('data.last_customer_reply_at', null);
});
