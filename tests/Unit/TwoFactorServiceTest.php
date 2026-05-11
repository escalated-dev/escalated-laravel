<?php

use Escalated\Laravel\Services\TwoFactorService;

beforeEach(function () {
    $this->service = new TwoFactorService;
});

it('generates a base32 secret', function () {
    $secret = $this->service->generateSecret();

    expect($secret)->toHaveLength(16);
    expect($secret)->toMatch('/^[A-Z2-7]+$/');
});

it('builds an otpauth uri', function () {
    $uri = $this->service->generateQrUri('ABCDEFGHIJKLMNOP', 'admin@example.com');

    expect($uri)->toStartWith('otpauth://totp/');
    expect($uri)->toContain('secret=ABCDEFGHIJKLMNOP');
    expect($uri)->toContain('issuer=');
});

it('generates eight recovery codes', function () {
    $codes = $this->service->generateRecoveryCodes();

    expect($codes)->toHaveCount(8);
    expect($codes[0])->toMatch('/^[A-F0-9]{8}-[A-F0-9]{8}$/');
});
