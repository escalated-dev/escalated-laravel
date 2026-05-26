<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;

function mobileCustomer(array $attributes = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'Mobile Customer',
        'email' => 'mobile-'.uniqid().'@example.com',
        'password' => bcrypt('password123'),
    ], $attributes));

    $result = ApiToken::createToken($user, 'Mobile Token', ['customer']);

    return [
        'user' => $user,
        'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']],
    ];
}

it('lists only the authenticated customers tickets in the mobile api', function () {
    $customer = mobileCustomer();
    $other = mobileCustomer();

    Ticket::create([
        'reference' => 'ESC-10101',
        'requester_type' => $customer['user']->getMorphClass(),
        'requester_id' => $customer['user']->getKey(),
        'subject' => 'My Ticket',
        'description' => 'Mine',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Medium,
    ]);

    Ticket::create([
        'reference' => 'ESC-10102',
        'requester_type' => $other['user']->getMorphClass(),
        'requester_id' => $other['user']->getKey(),
        'subject' => 'Other Ticket',
        'description' => 'Not mine',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Medium,
    ]);

    $this->getJson('/support/api/v1/mobile/tickets', $customer['headers'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.reference', 'ESC-10101');
});

it('creates, replies to, closes, reopens, and rates a customer ticket in the mobile api', function () {
    $customer = mobileCustomer();
    $department = Department::create([
        'name' => 'Support',
        'slug' => 'support',
        'is_active' => true,
    ]);

    $created = $this->postJson('/support/api/v1/mobile/tickets', [
        'subject' => 'Payout missing',
        'description' => 'I need help with a payout.',
        'priority' => 'high',
        'department_id' => $department->id,
    ], $customer['headers'])->assertStatus(201)->json();

    $reference = $created['data']['reference'];

    $this->postJson("/support/api/v1/mobile/tickets/{$reference}/replies", [
        'body' => 'Here is more detail.',
    ], $customer['headers'])->assertStatus(201);

    $this->getJson("/support/api/v1/mobile/tickets/{$reference}", $customer['headers'])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Payout missing')
        ->assertJsonPath('data.status.value', 'open')
        ->assertJsonPath('data.replies.0.body', 'Here is more detail.');

    $this->postJson("/support/api/v1/mobile/tickets/{$reference}/close", [], $customer['headers'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'closed');

    $this->postJson("/support/api/v1/mobile/tickets/{$reference}/reopen", [], $customer['headers'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'reopened');

    Ticket::where('reference', $reference)->update([
        'status' => TicketStatus::Resolved->value,
        'resolved_at' => now(),
    ]);

    $this->postJson("/support/api/v1/mobile/tickets/{$reference}/rate", [
        'rating' => 5,
        'comment' => 'Resolved quickly.',
    ], $customer['headers'])->assertOk();

    expect(SatisfactionRating::query()->whereHas('ticket', fn ($query) => $query->where('reference', $reference))->exists())->toBeTrue();
});

it('supports guest ticket creation and guest replies in the mobile api', function () {
    EscalatedSettings::set('guest_tickets_enabled', 'true');

    $created = $this->postJson('/support/api/v1/mobile/guest/tickets', [
        'name' => 'Guest Rider',
        'email' => 'guest@example.com',
        'subject' => 'Need help',
        'description' => 'I cannot track my order.',
        'priority' => 'medium',
    ])->assertStatus(201)->json();

    $guestToken = $created['data']['guest_access_token'];

    $this->getJson("/support/api/v1/mobile/guest/tickets/{$guestToken}")
        ->assertOk()
        ->assertJsonPath('data.reference', 'ESC-00001');

    $this->postJson("/support/api/v1/mobile/guest/tickets/{$guestToken}/replies", [
        'email' => 'guest@example.com',
        'body' => 'Additional information',
    ])->assertStatus(201);

    $this->getJson("/support/api/v1/mobile/guest/tickets/{$guestToken}")
        ->assertOk()
        ->assertJsonPath('data.replies.0.body', 'Additional information');
});
