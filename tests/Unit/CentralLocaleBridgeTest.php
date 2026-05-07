<?php

use Illuminate\Support\Facades\App;

it('bridges install translations from the central locale package', function () {
    // Sanity: the central package is what `EscalatedServiceProvider::resolveCentralLocalesPath`
    // expects to find. If composer didn't install it, the bridge can't fire.
    $centralPath = __DIR__.'/../../vendor/escalated-dev/locale/locales';
    expect(is_dir($centralPath))
        ->toBeTrue('Central locale package not installed at vendor/escalated-dev/locale/locales — run composer install');

    // The translator's loaded array should contain the bridged 'commands' group
    // under the 'escalated' namespace for any locale we look up against.
    App::setLocale('en');
    __('escalated::commands.install.installing');

    $loaded = (function () {
        return $this->loaded;
    })->call(app('translator'));

    expect($loaded)
        ->toHaveKey('escalated')
        ->and($loaded['escalated'])->toHaveKey('commands')
        ->and($loaded['escalated']['commands'])->toHaveKey('en');
});

it('resolves install keys to the central JSON values, not the PHP fallback', function () {
    $centralEn = json_decode(
        file_get_contents(__DIR__.'/../../vendor/escalated-dev/locale/locales/en.json'),
        true,
    );
    $centralFr = json_decode(
        file_get_contents(__DIR__.'/../../vendor/escalated-dev/locale/locales/fr.json'),
        true,
    );

    // Sample of keys consumed by InstallCommand. Pick ones that EXIST in central,
    // so a missing key would surface as an obvious test failure.
    $samples = [
        'installing' => 'commands.install.installing',
        'publishingConfig' => 'commands.install.publishingConfig',
        'seedingPermissions' => 'commands.install.seedingPermissions',
        'stepSeed' => 'commands.install.stepSeed',
    ];

    foreach ($samples as $name => $path) {
        $segments = explode('.', $path);
        $expectedEn = $centralEn;
        $expectedFr = $centralFr;
        foreach ($segments as $s) {
            $expectedEn = $expectedEn[$s] ?? null;
            $expectedFr = $expectedFr[$s] ?? null;
        }
        expect($expectedEn)->toBeString("Central en.json missing $path");
        expect($expectedFr)->toBeString("Central fr.json missing $path");

        App::setLocale('en');
        expect(__("escalated::$path"))->toBe($expectedEn, "$name (en) should match central");

        App::setLocale('fr');
        expect(__("escalated::$path"))->toBe($expectedFr, "$name (fr) should match central");
    }
});

it('rewrites central {placeholder} syntax to Laravel :placeholder for replacements', function () {
    // migrationsAlreadyPublished in central is "{count} Escalated migration(s) ...".
    // The bridge should rewrite it to ":count ..." so __() can substitute.
    App::setLocale('en');
    $rendered = __('escalated::commands.install.migrationsAlreadyPublished', ['count' => 7]);

    expect($rendered)
        ->toContain('7')
        ->and($rendered)->not->toContain('{count}')
        ->and($rendered)->not->toContain(':count');
});
