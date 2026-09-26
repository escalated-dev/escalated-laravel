<?php

/*
 * The report screens are handed their data in the shape they read.
 *
 * ReportScreenPropsTest proves each screen gets the right prop names. This is
 * the next layer down. Every chart in @escalated-dev/escalated reads a list of
 * `{label, value}`, the agent and cohort tables read their own column names,
 * and the SLA screen filters `at_risk_tickets` as a list of tickets. These
 * screens were sent ReportingService rows as they are (`bucket`/`count`,
 * `period`/`total_breaches`, `group`/`avg`, a keyed forecast), so every chart
 * drew bars of NaN height and the SLA screen threw on `.filter` -- with every
 * status and prop-name test passing.
 */

use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    $this->admin = $this->createAdmin();
    $department = Department::create(['name' => 'Billing', 'slug' => 'billing']);

    foreach (range(1, 3) as $i) {
        Ticket::factory()->create([
            'created_at' => now()->subDays($i),
            'first_response_at' => now()->subDays($i)->addHours($i),
            'resolved_at' => now()->subDays($i)->addHours($i * 6),
            'assigned_to' => $this->admin->id,
            'department_id' => $department->id,
            'sla_policy_id' => 1,
            'sla_first_response_breached' => $i === 2,
        ]);
    }

    $this->atRisk = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addHours(3),
        'sla_policy_id' => 1,
    ]);
});

function screenProps(string $routeName): array
{
    return test()->actingAs(test()->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route($routeName, ['days' => 30]))
        ->assertOk()
        ->json('props');
}

function expectChartSeries(mixed $series, string $name): void
{
    expect($series)->toBeList("{$name} is not a list")->not->toBeEmpty("{$name} is empty");

    foreach ($series as $point) {
        expect(array_keys($point))->toBe(['label', 'value'], "{$name} points are not {label, value}")
            ->and($point['label'])->toBeString()
            ->and($point['value'])->toBeNumeric();
    }
}

it('sends the response-times charts and agent table in the shape the screen reads', function () {
    $props = screenProps('escalated.admin.reports.response-times');

    foreach (['distribution', 'trend', 'by_department', 'by_priority'] as $chart) {
        expectChartSeries($props[$chart], $chart);
    }

    expect(collect($props['distribution'])->sum('value'))->toBe(3)
        ->and($props['by_department'][0]['label'])->toBe('Billing')
        ->and($props['by_agent'][0])->toMatchArray([
            'agent_id' => $this->admin->id,
            'agent_name' => $this->admin->name,
            'count' => 3,
        ])
        ->and($props['by_agent'][0])->toHaveKeys(['avg', 'median', 'p90']);
});

it('sends the resolution-times charts and agent table in the shape the screen reads', function () {
    $props = screenProps('escalated.admin.reports.resolution-times');

    foreach (['distribution', 'trend', 'by_department', 'by_channel'] as $chart) {
        expectChartSeries($props[$chart], $chart);
    }

    expect($props['by_agent'][0])->toHaveKeys(['agent_id', 'agent_name', 'count', 'avg', 'median', 'p90']);
});

it('sends the SLA screen its charts and the tickets at risk as a list', function () {
    $props = screenProps('escalated.admin.reports.sla-trends');

    foreach (['breach_trend', 'breach_by_department', 'breach_by_priority'] as $chart) {
        expectChartSeries($props[$chart], $chart);
    }

    expect($props['breach_by_type_trend'][0])->toHaveKeys(['label', 'values'])
        ->and($props['breach_by_type_trend'][0]['values'])->toHaveCount(2)
        ->and($props['at_risk_tickets'])->toBeList()->toHaveCount(1)
        ->and($props['at_risk_tickets'][0])->toMatchArray([
            'id' => $this->atRisk->id,
            'reference' => $this->atRisk->reference,
        ])
        ->and($props['at_risk_tickets'][0]['hours_remaining'])->toBeGreaterThan(2)->toBeLessThanOrEqual(3);
});

it('sends the agent ranking rows under the columns the screen sorts on', function () {
    $agent = screenProps('escalated.admin.reports.agent-ranking')['agents'][0];

    expect(array_keys($agent))->toEqualCanonicalizing([
        'agent_id', 'agent_name', 'volume', 'resolution_rate', 'avg_frt', 'avg_resolution', 'csat', 'composite_score',
    ])
        ->and($agent['volume'])->toBe(3)
        ->and($agent['avg_resolution'])->toBeGreaterThan(0);
});

it('sends every cohort tab as rows with a name', function () {
    $props = screenProps('escalated.admin.reports.cohorts');

    foreach (['by_department', 'by_channel', 'by_type', 'by_priority'] as $tab) {
        expect($props[$tab])->toBeList()->not->toBeEmpty();

        foreach ($props[$tab] as $row) {
            expect(array_keys($row))->toBe(['name', 'volume', 'avg_resolution', 'breach_rate', 'csat'], $tab)
                ->and($row['name'])->toBeString();
        }
    }

    expect(collect($props['by_department'])->pluck('name'))->toContain('Billing');
});
