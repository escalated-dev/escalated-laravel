<?php

/*
 * Every page name this package renders resolves to a component in the shared
 * frontend package.
 *
 * Inertia resolving a name to nothing is not an error. The response is a 200,
 * the resolver returns undefined, Vue renders nothing, and the panel comes up
 * blank -- which reads as a permissions problem or an empty dataset. Four of
 * the report screens in this package shipped that way, and a route test
 * asserting a 200 said they were fine the whole time.
 *
 * Neither repo's tests can see it alone: a controller test asserts a status,
 * and the frontend never hears the name. This is the comparison, against the
 * manifest the frontend package publishes and this repo vendors at
 * tests/Fixtures/escalated-pages.json.
 *
 * Adding a screen goes: component into the frontend, frontend release, refresh
 * the fixture, then render the name here. In that order, or it ships blank.
 */
const MANIFEST = __DIR__.'/../Fixtures/escalated-pages.json';

/**
 * @return list<string>
 */
function shippedPages(): array
{
    return json_decode((string) file_get_contents(MANIFEST), true, flags: JSON_THROW_ON_ERROR)['pages'];
}

/**
 * Page names rendered anywhere in src/, mapped to the files that render them,
 * so a failure can name the file and not only the string.
 *
 * @return array<string, list<string>>
 */
function renderedPages(): array
{
    $found = [];
    $root = dirname(__DIR__, 2).'/src';

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (! preg_match_all("/'(Escalated\/[A-Za-z0-9\/_]+)'/", $source, $matches)) {
            continue;
        }

        foreach ($matches[1] as $page) {
            $found[$page][] = basename($file->getPathname());
        }
    }

    return $found;
}

/**
 * @param  list<string>  $missing
 * @param  array<string, list<string>>  $rendered
 */
function explainMissingPages(array $missing, array $rendered): string
{
    $lines = ['these page names have no component in @escalated-dev/escalated, so they render a blank panel:'];

    foreach ($missing as $page) {
        $lines[] = sprintf('  %s  (%s)', $page, implode(', ', array_unique($rendered[$page])));
    }

    $lines[] = '';
    $lines[] = 'Either the name is wrong, or the component has not been released yet.';
    $lines[] = 'If it has been: refresh tests/Fixtures/escalated-pages.json from the package.';

    return implode("\n", $lines);
}

it('renders only page names the frontend ships', function () {
    $rendered = renderedPages();

    expect($rendered)->not->toBeEmpty('found no page names at all, which means this test is not looking where it should');

    $missing = array_values(array_diff(array_keys($rendered), shippedPages()));
    sort($missing);

    expect($missing)->toBe([], explainMissingPages($missing, $rendered));
});

it('has a manifest that is present and looks like one', function () {
    // A fixture gone missing or empty would make the test above pass by
    // comparing against nothing.
    expect(file_exists(MANIFEST))->toBeTrue();
    expect(count(shippedPages()))->toBeGreaterThan(50);
    expect(array_filter(shippedPages(), fn ($page) => ! str_starts_with($page, 'Escalated/')))->toBe([]);
});
