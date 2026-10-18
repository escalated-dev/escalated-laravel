<?php

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Support\WidgetOrigins;
use Illuminate\Http\Request;

it('normalises admin-entered origins and rejects anything that is not a bare origin', function (string $entry, ?string $expected) {
    expect(WidgetOrigins::normalizePattern($entry))->toBe($expected);
})->with([
    ['https://Shop.Example.com', 'https://shop.example.com'],
    ['https://shop.example.com/', 'https://shop.example.com'],
    ['https://shop.example.com:443', 'https://shop.example.com'],
    ['http://localhost:5173', 'http://localhost:5173'],
    ['https://*.example.com', 'https://*.example.com'],
    ['https://*.example.com:8443', 'https://*.example.com:8443'],
    ['https://shop.example.com/checkout', null],
    ['https://shop.example.com?x=1', null],
    ['shop.example.com', null],
    ['*', null],
    ['https://*', null],
    ['https://a.*.example.com', null],
    ['ftp://shop.example.com', null],
    ['https://user@shop.example.com', null],
]);

it('matches exact origins, ports and subdomain wildcards', function (string $origin, bool $allowed) {
    config(['escalated.widget.allowed_origins' => ['https://shop.example.com', 'https://*.brand.test', 'http://localhost:5173']]);

    expect(WidgetOrigins::allows($origin))->toBe($allowed);
})->with([
    ['https://shop.example.com', true],
    ['https://SHOP.example.com:443', true],
    ['http://shop.example.com', false],
    ['https://shop.example.com:8443', false],
    ['https://help.brand.test', true],
    ['https://a.b.brand.test', true],
    ['https://brand.test', false],
    ['https://notbrand.test', false],
    ['https://help.brand.test.evil.com', false],
    ['http://localhost:5173', true],
    ['http://localhost', false],
    ['null', false],
]);

it('merges the config list with the admin setting', function () {
    config(['escalated.widget.allowed_origins' => ['https://shop.example.com']]);
    EscalatedSettings::set('widget_allowed_origins', "https://blog.example.com, https://bad.example.com/path\n");

    expect(WidgetOrigins::configured())->toBe(['https://shop.example.com', 'https://blog.example.com'])
        ->and(WidgetOrigins::allowsConfigured('https://blog.example.com'))->toBeFalse();
});

it('only reports an Origin that names another site', function (?string $origin, ?string $expected) {
    $request = Request::create('https://help.example.com/support/widget/config', 'GET', server: array_filter(['HTTP_ORIGIN' => $origin]));

    expect(WidgetOrigins::crossOrigin($request))->toBe($expected);
})->with([
    [null, null],
    ['null', null],
    ['https://help.example.com', null],
    ['https://help.example.com:443', null],
    ['https://shop.example.com', 'https://shop.example.com'],
    ['http://help.example.com', 'http://help.example.com'],
]);
