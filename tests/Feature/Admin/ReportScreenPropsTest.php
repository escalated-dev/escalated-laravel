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
