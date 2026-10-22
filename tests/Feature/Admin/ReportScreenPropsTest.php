<?php

/*
 * The report screens are handed the props they actually read.
 *
 * A page name that resolves is not a screen that works. Inertia passes props by
 * name, and a name the component does not declare is not passed at all -- it
 * lands on the root element as an attribute. The screen renders its defaults,
 * which for a report is zeroes and empty charts, on a 200.
 *
 * That is indistinguishable from a quiet week, which is why SlaTrends,
 * AgentRanking and Comparison rendered empty here for as long as they did while
 * every test on them passed.
 *
 * The manifest at tests/Fixtures/escalated-pages.json publishes what each
 * component declares. This asserts the response against it.
 */

use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

const PROPS_MANIFEST = __DIR__.'/../../Fixtures/escalated-pages.json';

/**
 * @return array{props: list<string>, required: list<string>}
 */
function declaredProps(string $page): array
{
    $manifest = json_decode((string) file_get_contents(PROPS_MANIFEST), true, flags: JSON_THROW_ON_ERROR);

    // toHaveKey's second argument is an expected value, not a message.
    expect(array_key_exists($page, $manifest['props']))->toBeTrue("the manifest does not describe {$page}");

    return $manifest['props'][$page];
}

it('sends report screens every prop they declare, and nothing they do not', function (string $routeName, string $page) {
    $admin = $this->createAdmin();

    // No fixtures. Which props a screen sends is not a function of how much
    // data there is, and creating tickets here moved the reference sequence
    // under a mobile-API test that asserts on one.
    $response = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route($routeName, ['days' => 30]));

    $response->assertOk();
    expect($response->json('component'))->toBe($page);

    $declared = declaredProps($page);
    $sent = array_keys($response->json('props'));

    // Inertia shares some props with every page; those are not this screen's
    // business and the component never declares them.
    $shared = ['errors', 'auth', 'flash', 'escalated', 'ziggy'];
    $sent = array_values(array_diff($sent, $shared));

    $missing = array_values(array_diff($declared['props'], $sent));
    $unread = array_values(array_diff($sent, $declared['props']));

    expect($missing)->toBe([], sprintf(
        "%s declares props this response never sends, so they render as their defaults:\n  %s",
        $page,
        implode("\n  ", $missing)
    ));

    expect($unread)->toBe([], sprintf(
        "%s is sent props it does not declare, so they are dropped on the root element:\n  %s",
        $page,
        implode("\n  ", $unread)
    ));
})->with([
    ['escalated.admin.reports.sla-trends', 'Escalated/Admin/Reports/SlaTrends'],
    ['escalated.admin.reports.frt', 'Escalated/Admin/Reports/ResponseTimes'],
    ['escalated.admin.reports.resolution', 'Escalated/Admin/Reports/ResolutionTimes'],
    ['escalated.admin.reports.response-times', 'Escalated/Admin/Reports/ResponseTimes'],
    ['escalated.admin.reports.resolution-times', 'Escalated/Admin/Reports/ResolutionTimes'],
    ['escalated.admin.reports.agent-ranking', 'Escalated/Admin/Reports/AgentRanking'],
    ['escalated.admin.reports.cohorts', 'Escalated/Admin/Reports/Cohorts'],
    ['escalated.admin.reports.comparison', 'Escalated/Admin/Reports/Comparison'],
]);

it('has a manifest that describes props at all', function () {
    // A fixture that lost its props would make every case above pass by
    // comparing two empty lists.
    $manifest = json_decode((string) file_get_contents(PROPS_MANIFEST), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toHaveKey('props');
    expect(count($manifest['props']))->toBeGreaterThan(50);
    expect($manifest['props']['Escalated/Admin/Reports/AgentRanking']['props'])->toBe(['agents', 'period_days']);
});

it('sends the agent detail screen the props it reads, and the figures behind them', function () {
    $admin = $this->createAdmin();
    $agent = $this->createAgent(['name' => 'Ada Agent', 'email' => 'ada@example.com']);

    $created = now()->subDays(2)->startOfHour();
    $answered = Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => $created,
        'first_response_at' => $created->copy()->addHours(2),
        'resolved_at' => $created->copy()->addHours(4),
    ]);
    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => $created,
        'first_response_at' => $created->copy()->addHours(4),
    ]);
    // The period before: one ticket, so the comparison has something to show.
    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDays(40),
    ]);
    // Someone else's ticket is not this agent's.
    Ticket::factory()->create(['created_at' => $created]);

    SatisfactionRating::create(['ticket_id' => $answered->id, 'rating' => 4]);
    Reply::create([
        'ticket_id' => $answered->id,
        'author_type' => $agent->getMorphClass(),
        'author_id' => $agent->getKey(),
        'body' => 'On it',
        'is_internal_note' => false,
        'created_at' => $created->copy()->addHours(2),
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.admin.reports.agent-detail', ['id' => $agent->id, 'days' => 30]));

    $response->assertOk();
    expect($response->json('component'))->toBe('Escalated/Admin/Reports/AgentDetail');

    $sent = array_values(array_diff(array_keys($response->json('props')), ['errors', 'auth', 'flash', 'escalated', 'ziggy']));
    $declared = declaredProps('Escalated/Admin/Reports/AgentDetail')['props'];
    sort($sent);
    expect($sent)->toBe($declared);

    $props = $response->json('props');
    $day = $created->toDateString();

    expect($props['period_days'])->toBe(30)
        ->and($props['agent'])->toBe(['id' => $agent->id, 'name' => 'Ada Agent', 'email' => 'ada@example.com'])
        ->and($props['total_tickets'])->toBe(2)
        ->and($props['resolved_tickets'])->toBe(1)
        ->and((float) $props['avg_frt'])->toBe(3.0)
        ->and((float) $props['avg_resolution'])->toBe(4.0)
        ->and((float) $props['csat_score'])->toBe(4.0)
        ->and(array_keys($props['response_percentiles']))->toBe(['p50', 'p75', 'p90', 'p95', 'p99'])
        ->and((float) $props['response_percentiles']['p50'])->toBe(3.0)
        ->and($props['productivity'])->toBe([['label' => $day, 'value' => 2, 'values' => [1, 1]]])
        ->and($props['workload'])->toBe([['label' => $day, 'value' => 2]])
        ->and($props['csat_breakdown'])->toBe([['label' => '4 stars', 'value' => 1]])
        ->and($props['prev_total_tickets'])->toBe(1)
        ->and($props['prev_resolved_tickets'])->toBe(0)
        ->and($props['prev_csat_score'])->toBeNull();
});

it('renders the agent detail screen for an agent with no tickets', function () {
    $admin = $this->createAdmin();
    $agent = $this->createAgent();

    $props = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.admin.reports.agent-detail', ['id' => $agent->id]))
        ->assertOk()
        ->json('props');

    expect($props['total_tickets'])->toBe(0)
        ->and($props['csat_score'])->toBeNull()
        ->and($props['productivity'])->toBe([])
        ->and($props['workload'])->toBe([])
        ->and($props['csat_breakdown'])->toBe([])
        ->and($props)->not->toHaveKeys(['agent_id', 'metrics', 'percentiles']);
});
