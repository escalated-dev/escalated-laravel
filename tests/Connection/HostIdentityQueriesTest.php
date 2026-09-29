<?php

use Escalated\Laravel\Drivers\LocalDriver;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ReportingService;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class OtherHostRequester extends TestUser
{
    protected $table = 'other_requesters';
}

beforeEach(function () {
    $this->requester = $this->createTestUser(['name' => 'Customer Identity', 'email' => 'customer@example.com']);
    $this->agent = $this->createAgent(['name' => 'Agent Identity']);
    $this->ticket = Ticket::create([
        'subject' => 'A delivery question',
        'description' => 'Package has not arrived',
        'requester_type' => $this->requester->getMorphClass(),
        'requester_id' => $this->requester->getKey(),
        'assigned_to' => $this->agent->getKey(),
        'status' => 'resolved',
        'priority' => 'medium',
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHour(),
        'resolved_at' => now()->subDay()->addHours(2),
    ]);
    SatisfactionRating::create(['ticket_id' => $this->ticket->id, 'rating' => 5]);
    Reply::create([
        'ticket_id' => $this->ticket->id,
        'author_type' => $this->agent->getMorphClass(),
        'author_id' => $this->agent->getKey(),
        'body' => 'We found your parcel',
        'is_internal_note' => false,
    ]);
});

it('runs every agent report without a users table on the package connection', function (string $method, bool $dates) {
    expect(Schema::connection('escalated')->hasTable('users'))->toBeFalse();

    $report = app(ReportingService::class)->{$method}(...($dates ? [now()->subWeek(), now()] : [7]));

    expect($report)->toHaveCount(1)
        ->and((array) $report[0])->toMatchArray(['agent_name' => 'Agent Identity']);
})->with([
    ['getAgentPerformance', true],
    ['getCsatByAgent', true],
    ['agentPerformanceRanking', false],
    ['agentWorkloadDistribution', false],
    ['agentProductivity', false],
]);

it('searches requester identities on the host and keeps ticket filtering on the package connection', function () {
    expect(Ticket::search('Customer Identity')->pluck('id')->all())->toBe([$this->ticket->id])
        ->and(Ticket::search('customer@example.com')->pluck('id')->all())->toBe([$this->ticket->id])
        ->and(Ticket::search('unknown@example.com')->count())->toBe(0);

    $driver = app(LocalDriver::class);
    expect($driver->listTickets(['search' => 'Customer Identity'])->items())->toHaveCount(1)
        ->and($driver->listTickets(['requester' => 'customer@example.com'])->items())->toHaveCount(1)
        ->and($driver->listTickets(['requester' => 'Customer Identity', 'status' => 'open'])->total())->toBe(0);
});

it('honours morph aliases when resolving host identities', function () {
    Relation::morphMap(['person' => $this->agent::class]);

    try {
        $this->ticket->updateQuietly(['requester_type' => 'person']);
        Reply::query()->update(['author_type' => 'person']);

        expect(Ticket::search('Customer Identity')->count())->toBe(1)
            ->and(app(ReportingService::class)->agentProductivity(7)[0]['total_replies'])->toBe(1);
    } finally {
        Relation::morphMap([], false);
    }
});

it('uses the configured host display column and names agents with resolutions but no replies', function () {
    config(['escalated.user_display_column' => 'email']);
    Escalated::flushColumnCache();
    Reply::query()->forceDelete();

    $report = app(ReportingService::class)->agentProductivity(7);

    expect($report[0]['agent_name'])->toBe('agent@example.com')
        ->and($report[0]['total_resolved'])->toBe(1)
        ->and($report[0]['total_replies'])->toBe(0);
});

it('keeps same-named agents in separate workload series', function () {
    $other = $this->createAgent(['name' => 'Agent Identity', 'email' => 'other@example.com']);
    $this->ticket->replicate()->fill(['reference' => 'ESC-OTHER', 'assigned_to' => $other->getKey()])->save();

    $report = app(ReportingService::class)->agentWorkloadDistribution(7);

    expect($report)->toHaveCount(2);
});

it('omits agent identities deleted from the host without querying host tables on the package connection', function () {
    $this->agent->delete();

    expect(app(ReportingService::class)->getAgentPerformance(now()->subWeek(), now()))->toBe([])
        ->and(app(ReportingService::class)->getCsatByAgent(now()->subWeek(), now()))->toBe([]);
});

it('keeps requester types paired with matching IDs', function () {
    Schema::connection('testing')->create('other_requesters', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
    $other = OtherHostRequester::create([
        'id' => $this->requester->id, 'name' => 'Different person', 'email' => 'different@example.com',
    ]);
    $ticket = $this->ticket->replicate()->fill([
        'reference' => 'ESC-DIFFERENT', 'requester_type' => $other->getMorphClass(),
        'requester_id' => $other->getKey(),
    ]);
    $ticket->save();

    expect(Ticket::search('Customer Identity')->pluck('id')->all())->toBe([$this->ticket->id])
        ->and(Ticket::search('Different person')->pluck('id')->all())->toBe([$ticket->id]);
});

it('serves report screens and agent CSV exports through HTTP on separate databases', function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $admin = $this->createAdmin();

    $this->actingAs($admin)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.admin.reports.dashboard'))
        ->assertOk()->assertJsonPath('props.agent_performance.0.agent_name', 'Agent Identity');

    $this->flushHeaders()->get(route('escalated.admin.reports.export', [
        'type' => 'agent_performance', 'format' => 'csv', 'period' => 7,
    ]))->assertOk()->assertSee('Agent Identity');

    $this->get(route('escalated.admin.reports.export', [
        'type' => 'csat', 'format' => 'csv', 'period' => 7,
    ]))->assertOk()->assertSee('Agent Identity');
});
