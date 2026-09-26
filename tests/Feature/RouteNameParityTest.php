<?php

use Illuminate\Support\Facades\Route;

/*
 * Every route name the shared frontend asks for is one this package registers.
 *
 * The frontend builds links with Ziggy's route(). An unknown name, or a known
 * name missing a required parameter, throws while the component renders, so
 * the whole screen fails rather than one link. The Reports dashboard linked to
 * `reports.response-times` and `reports.resolution-times`, which this package
 * called `reports.frt` and `reports.resolution`, and the Workflows index asked
 * for `workflows.logs` with no workflow. Both screens died in the browser while
 * their controller tests reported 200.
 *
 * tests/Fixtures/escalated-route-names.json is the list, extracted from the
 * frontend's src/ with:
 *
 *   grep -rhoE "route\(\s*['\"`]escalated\.[A-Za-z0-9_.-]+|route:\s*'escalated\.[A-Za-z0-9_.-]+" src
 *
 * (quotes and the `route(` / `route:` prefix stripped, sorted, de-duplicated).
 */
const ROUTE_MANIFEST = __DIR__.'/../Fixtures/escalated-route-names.json';

/**
 * Names the frontend calls only inside a guard with a fallback, so a host
 * without them still renders.
 */
const OPTIONAL_FRONTEND_ROUTES = [
    // AttachmentList and ChatBubble prefer attachment.url, then try this name
    // in a try/catch, then fall back to a fixed path.
    'escalated.attachments.download',
    // TwoFactorChallenge posts to its `action` prop when one is given.
    'escalated.two-factor.verify',
];

/**
 * Route parameters every screen can build a link without.
 */
const PARAMETERLESS_FRONTEND_ROUTES = [
    'escalated.admin.workflows.logs',
    'escalated.admin.reports.response-times',
    'escalated.admin.reports.resolution-times',
];

it('registers every route name the frontend links to', function () {
    $manifest = json_decode((string) file_get_contents(ROUTE_MANIFEST), true, flags: JSON_THROW_ON_ERROR);

    $missing = array_values(array_filter(
        array_diff($manifest['routes'], OPTIONAL_FRONTEND_ROUTES),
        fn (string $name) => ! Route::has($name),
    ));

    expect($missing)->toBe([], 'The frontend links to route names this package does not register: '.implode(', ', $missing));
});

it('builds the links the frontend makes without parameters', function (string $name) {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route->parameterNames())->toBe([]);
})->with(PARAMETERLESS_FRONTEND_ROUTES);
