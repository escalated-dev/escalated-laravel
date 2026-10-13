<?php

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Events\TicketAssigned;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\NewTicketNotification;
use Escalated\Laravel\Policies\TicketPolicy;
use Escalated\Laravel\Services\ReportExportService;
use Escalated\Laravel\Services\ReportingService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $this->app->bind(EscalatedUiRenderer::class, fn () => new class implements EscalatedUiRenderer
    {
        public function render(string $page, array $props = []): mixed
        {
            return response()->json($props);
        }
    });
    $this->agent = $this->createAgent();
    $this->department = Department::factory()->create();
    $this->department->agents()->attach($this->agent);
    $this->visible = Ticket::factory()->create(['department_id' => $this->department->id, 'subject' => 'Shared search term']);
    $this->hidden = Ticket::factory()->create(['department_id' => Department::factory()->create()->id, 'subject' => 'Shared search term', 'priority' => 'low']);
    $this->unallocated = Ticket::factory()->create(['department_id' => null]);
    config(['escalated.visibility' => 'department']);
});

it('scopes the agent list and search even with a foreign department filter', function () {
    $this->actingAs($this->agent)->getJson(route('escalated.agent.tickets.index'))
        ->assertOk()->assertJsonPath('tickets.total', 1)
        ->assertJsonPath('tickets.data.0.id', $this->visible->id);
    $this->getJson(route('escalated.agent.tickets.index', ['search' => 'Shared search term']))
        ->assertOk()->assertJsonPath('tickets.total', 1);
    $this->getJson(route('escalated.agent.tickets.index', ['department_id' => $this->hidden->department_id]))
        ->assertOk()->assertJsonPath('tickets.total', 0);
});

it('denies each ticket policy operation outside an agents departments', function (string $ability) {
    $policy = new TicketPolicy;
    expect($policy->{$ability}($this->agent, $this->visible))->toBeTrue()
        ->and($policy->{$ability}($this->agent, $this->hidden))->toBeFalse()
        ->and($policy->{$ability}($this->agent, $this->unallocated))->toBeFalse();
})->with(['view', 'update', 'reply', 'addNote', 'assign', 'close']);

it('scopes direct model queries and has no requester or assignee exception for agents', function () {
    $this->hidden->updateQuietly(['requester_type' => $this->agent->getMorphClass(), 'requester_id' => $this->agent->id, 'assigned_to' => $this->agent->id]);
    $this->actingAs($this->agent);
    expect(Ticket::pluck('id')->all())->toBe([$this->visible->id])
        ->and((new TicketPolicy)->view($this->agent, $this->hidden))->toBeFalse();
    $this->getJson(route('escalated.agent.tickets.show', $this->hidden))->assertNotFound();
});

it('scopes bearer token API lists search dashboard and mutations', function () {
    $token = ApiToken::createToken($this->agent, 'Visibility', ['agent'])['plainTextToken'];
    $headers = ['Authorization' => 'Bearer '.$token];
    $this->getJson('/support/api/v1/tickets?search=Shared', $headers)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $this->visible->id);
    $this->getJson('/support/api/v1/dashboard', $headers)->assertOk()->assertJsonPath('stats.open', 1);
    $url = '/support/api/v1/tickets/'.$this->hidden->reference;
    $this->getJson($url, $headers)->assertNotFound();
    $this->postJson($url.'/reply', ['body' => 'Must not be sent'], $headers)->assertNotFound();
    $this->patchJson($url.'/status', ['status' => 'closed'], $headers)->assertNotFound();
    $this->postJson($url.'/assign', ['agent_id' => $this->agent->id], $headers)->assertNotFound();
});

it('does not mutate hidden tickets in a mixed bulk request', function () {
    $this->actingAs($this->agent)->post(route('escalated.agent.tickets.bulk'), [
        'ticket_ids' => [$this->visible->id, $this->hidden->id], 'action' => 'priority', 'value' => 'critical',
    ])->assertRedirect();
    expect($this->visible->fresh()->priority)->toBe(TicketPriority::Critical)
        ->and($this->hidden->fresh()->priority)->not->toBe(TicketPriority::Critical);
});

it('scopes report aggregates including raw joins and satisfaction ratings', function () {
    $this->visible->updateQuietly(['assigned_to' => $this->agent->id]);
    $this->hidden->updateQuietly(['assigned_to' => $this->agent->id]);
    SatisfactionRating::create(['ticket_id' => $this->visible->id, 'rating' => 5]);
    SatisfactionRating::create(['ticket_id' => $this->hidden->id, 'rating' => 1]);
    $this->actingAs($this->agent);
    $reports = app(ReportingService::class);
    expect(array_sum(array_column($reports->getTicketsByStatus(), 'value')))->toBe(1)
        ->and(array_sum(array_column($reports->ticketsByDepartment(7), 'volume')))->toBe(1)
        ->and($reports->getCsatAverage(now()->subDay(), now()->addDay()))->toBe(5.0)
        ->and($reports->getCsatByAgent(now()->subDay(), now()->addDay())[0]->total_ratings)->toBe(1);
});

it('applies department visibility to authenticated widget status access', function () {
    EscalatedSettings::set('widget_enabled', '1');
    $token = $this->guestToken($this->hidden);
    $this->actingAs($this->agent)
        ->getJson(route('escalated.widget.tickets.status', $this->hidden->reference), ['Authorization' => 'Bearer '.$token])
        ->assertNotFound();
});

it('retains verified guest widget access without a staff session', function () {
    EscalatedSettings::set('widget_enabled', '1');
    $token = $this->guestToken($this->hidden);
    $this->getJson(route('escalated.widget.tickets.status', $this->hidden->reference), ['Authorization' => 'Bearer '.$token])
        ->assertOk();
});

it('preserves all visibility by default and admin access in department mode', function () {
    config(['escalated.visibility' => 'all']);
    $this->actingAs($this->agent);
    expect(Ticket::count())->toBe(3);
    config(['escalated.visibility' => 'department']);
    $this->actingAs($this->createAdmin());
    expect(Ticket::count())->toBe(3);
});

it('returns no tickets for an agent with no departments and observes membership changes', function () {
    $this->actingAs($this->agent);
    expect(Ticket::count())->toBe(1);
    $this->department->agents()->detach($this->agent);
    expect(Ticket::count())->toBe(0);
});

it('does not restrict background queries without an authenticated actor', function () {
    expect(Ticket::count())->toBe(3);
});

it('denies broad realtime channels and checks ticket channels and notification recipients', function () {
    require __DIR__.'/../../routes/channels.php';
    $channels = Broadcast::driver()->getChannels();
    expect($channels['escalated.tickets']($this->agent))->toBeFalse()
        ->and($channels['escalated.chat.queue']($this->agent))->toBeFalse()
        ->and($channels['escalated.tickets.{ticketId}']($this->agent, $this->hidden->id))->toBeFalse()
        ->and($channels['escalated.tickets.{ticketId}']($this->agent, $this->visible->id))->toBeTrue();
    expect((new NewTicketNotification($this->hidden))->shouldSend($this->agent, 'mail'))->toBeFalse()
        ->and((new NewTicketNotification($this->visible))->shouldSend($this->agent, 'mail'))->toBeTrue();
    $channels = (new TicketAssigned($this->hidden, $this->agent->id))->broadcastOn();
    expect(array_map(fn ($channel) => $channel->name, $channels))->not->toContain('private-escalated.agents.'.$this->agent->id);
});

it('scopes report exports and raw reply and tag aggregates', function () {
    foreach ([$this->visible, $this->hidden] as $ticket) {
        $ticket->replies()->create(['body' => 'Reply', 'author_type' => $this->agent->getMorphClass(), 'author_id' => $this->agent->id, 'is_internal_note' => false]);
        $ticket->tags()->attach(Tag::factory()->create());
    }
    $this->actingAs($this->agent);
    $reports = app(ReportingService::class);
    expect(array_sum(array_column($reports->ticketsByTag(7), 'volume')))->toBe(1)
        ->and($reports->agentProductivity(7)[0]['total_replies'])->toBe(1);
    $export = app(ReportExportService::class)->exportToJson('tickets', ['period' => 7])->getData(true);
    $status = array_values(array_filter($export['data'], fn ($row) => $row['metric'] === 'by_status'));
    expect(array_sum(array_column($status, 'value')))->toBe(1);
});

it('preserves customer ownership and configured gates', function () {
    $customer = $this->createTestUser();
    $this->hidden->updateQuietly(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);
    $this->actingAs($customer)->getJson(route('escalated.customer.tickets.show', $this->hidden))->assertOk();
    config(['escalated.authorization.agent_gate' => 'custom-agent', 'escalated.authorization.admin_gate' => 'custom-admin']);
    Gate::define('custom-agent', fn ($user) => $user->id === $this->agent->id);
    Gate::define('custom-admin', fn () => false);
    $this->actingAs($this->agent)->getJson(route('escalated.agent.tickets.index'))->assertOk()->assertJsonPath('tickets.total', 1);
});
