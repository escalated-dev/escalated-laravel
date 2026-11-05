<?php

/*
 * Customer online status on agent pages (ADR 2026-11-05-agent-panel-data,
 * section 2): recorded from requests the customer already makes, throttled,
 * and never shown to customers.
 */

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\CustomerPresence;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    config(['escalated.agent_inbox.cache_seconds' => 0]);
    Carbon::setTestNow('2026-11-05 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function presenceCustomer(): TestUser
{
    return TestUser::create(['name' => 'Customer', 'email' => 'customer'.uniqid().'@example.com', 'password' => 'x']);
}

function presenceRow($test, $agent, Ticket $ticket): array
{
    $row = collect($test->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.index'))
        ->assertOk()
        ->json('props.tickets.data'))->firstWhere('id', $ticket->id);
    $test->flushHeaders();

    return [$row['requester_online'], $row['requester_last_seen_at']];
}

function visitPortal($test, $customer): void
{
    $test->actingAs($customer)->get(route('escalated.customer.tickets.index'))->assertOk();
}

it('shows a customer as online while the portal saw them within the window', function () {
    $agent = $this->createAgent();
    $customer = presenceCustomer();
    $ticket = Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);

    expect(presenceRow($this, $agent, $ticket))->toBe([false, null]);

    visitPortal($this, $customer);
    expect(presenceRow($this, $agent, $ticket))->toBe([true, '2026-11-05T10:00:00+00:00']);

    Carbon::setTestNow('2026-11-05 10:02:01');
    expect(presenceRow($this, $agent, $ticket))->toBe([false, '2026-11-05T10:00:00+00:00']);
});

it('uses the configured online window', function () {
    config(['escalated.presence.online_window_seconds' => 600]);
    $agent = $this->createAgent();
    $customer = presenceCustomer();
    $ticket = Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);

    visitPortal($this, $customer);
    Carbon::setTestNow('2026-11-05 10:09:00');

    expect(presenceRow($this, $agent, $ticket)[0])->toBeTrue();
});

it('writes at most once per customer every 30 seconds', function () {
    $customer = presenceCustomer();

    visitPortal($this, $customer);
    Carbon::setTestNow('2026-11-05 10:00:20');
    visitPortal($this, $customer);
    expect(CustomerPresence::sole()->last_seen_at->toIso8601String())->toBe('2026-11-05T10:00:00+00:00');

    Carbon::setTestNow('2026-11-05 10:00:31');
    visitPortal($this, $customer);
    expect(CustomerPresence::sole()->last_seen_at->toIso8601String())->toBe('2026-11-05T10:00:31+00:00');
});

it('records a guest from the widget chat through the ticket contact', function () {
    EscalatedSettings::set('chat_enabled', '1');
    $agent = $this->createAgent();
    $contact = Contact::findOrCreateByEmail('guest@example.com', 'Guest');
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat', 'contact_id' => $contact->id, 'guest_email' => 'guest@example.com']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);
    $token = $this->guestToken($ticket, 'chat');
    $session->update(['customer_session_id' => hash('sha256', $token)]);

    $this->getJson("/support/widget/chat/{$token}/messages")->assertOk();

    expect(presenceRow($this, $agent, $ticket))->toBe([true, '2026-11-05T10:00:00+00:00']);
});

it('records the customer from the widget ticket status lookup', function () {
    EscalatedSettings::set('widget_enabled', '1');
    $agent = $this->createAgent();
    $contact = Contact::findOrCreateByEmail('guest@example.com', 'Guest');
    $ticket = Ticket::factory()->create(['contact_id' => $contact->id, 'guest_email' => 'guest@example.com']);

    $this->withToken($this->guestToken($ticket))
        ->getJson(route('escalated.widget.tickets.status', $ticket->reference))
        ->assertOk()
        ->assertJsonMissingPath('requester_online')
        ->assertJsonMissingPath('requester_last_seen_at');
    $this->flushHeaders();

    expect(presenceRow($this, $agent, $ticket)[0])->toBeTrue();
});

it('sends presence on the agent ticket page', function () {
    $agent = $this->createAgent();
    $customer = presenceCustomer();
    $ticket = Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);
    visitPortal($this, $customer);

    $this->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.show', $ticket->reference))
        ->assertOk()
        ->assertJsonPath('props.ticket.requester_online', true)
        ->assertJsonPath('props.ticket.requester_last_seen_at', '2026-11-05T10:00:00+00:00');
});

it('does not record staff browsing the customer portal', function () {
    $agent = $this->createAgent();

    visitPortal($this, $agent);

    expect(CustomerPresence::count())->toBe(0);
});

it('never shows presence to customers', function () {
    $customer = presenceCustomer();
    $ticket = Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);
    visitPortal($this, $customer);

    $response = $this->actingAs($customer)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.customer.tickets.show', $ticket->reference))
        ->assertOk();

    expect($response->getContent())->not->toContain('requester_online')
        ->and($response->getContent())->not->toContain('requester_last_seen_at');
});

it('answers the agent list with a fixed number of presence queries', function () {
    $agent = $this->createAgent();
    foreach (range(1, 3) as $i) {
        $customer = presenceCustomer();
        Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);
    }

    $count = function () use ($agent) {
        Escalated::db()->flushQueryLog();
        Escalated::db()->enableQueryLog();
        presenceRow($this, $agent, Ticket::first());

        return collect(Escalated::db()->getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'customer_presence'))->count();
    };

    $few = $count();
    foreach (range(1, 5) as $i) {
        $customer = presenceCustomer();
        Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);
    }

    expect($few)->toBe(1)->and($count())->toBe(1);
});
