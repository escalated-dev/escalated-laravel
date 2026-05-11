<?php

use Escalated\Laravel\Models\TwoFactor;
use Escalated\Laravel\Services\TwoFactorService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('shows the two-factor settings page', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.two-factor.index'))
        ->assertOk();
});

it('starts two-factor setup and flashes qr data', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->post(route('escalated.admin.two-factor.setup'))
        ->assertRedirect()
        ->assertSessionHas('two_factor_setup');

    $record = TwoFactor::query()->where('user_id', $admin->getKey())->firstOrFail();
    expect($record->secret)->not->toBe('');
    expect($record->recovery_codes)->toHaveCount(8);
});

it('confirms a pending setup and flashes recovery codes after success', function () {
    $admin = $this->createAdmin();
    $service = app(TwoFactorService::class);
    $secret = $service->generateSecret();
    $code = currentTotp($secret, now()->timestamp);
    $recoveryCodes = $service->generateRecoveryCodes();

    $twoFactor = TwoFactor::create([
        'user_id' => $admin->getKey(),
        'secret' => $secret,
        'recovery_codes' => $recoveryCodes,
    ]);

    $this->actingAs($admin)
        ->from(route('escalated.admin.two-factor.index'))
        ->post(route('escalated.admin.two-factor.confirm'), ['code' => $code])
        ->assertRedirect(route('escalated.admin.two-factor.index'))
        ->assertSessionHas('two_factor_confirmed');

    expect($twoFactor->fresh()?->confirmed_at)->not->toBeNull();
});

it('disables two-factor for the current user', function () {
    $admin = $this->createAdmin();

    TwoFactor::create([
        'user_id' => $admin->getKey(),
        'secret' => 'ABCDEFGHIJKLMNOP',
        'recovery_codes' => ['code-1', 'code-2'],
        'confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('escalated.admin.two-factor.disable'))
        ->assertRedirect();

    expect(TwoFactor::query()->where('user_id', $admin->getKey())->exists())->toBeFalse();
});

function currentTotp(string $secret, int $timestamp): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $map = array_flip(str_split($alphabet));
    $binary = '';

    foreach (str_split(strtoupper($secret)) as $char) {
        $binary .= str_pad(decbin($map[$char] ?? 0), 5, '0', STR_PAD_LEFT);
    }

    $decoded = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $decoded .= chr(bindec(substr($binary, $i, 8)));
    }

    $timeSlice = (int) floor($timestamp / 30);
    $time = pack('N*', 0, $timeSlice);
    $hmac = hash_hmac('sha1', $time, $decoded, true);
    $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
    $code = (
        ((ord($hmac[$offset]) & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
        (ord($hmac[$offset + 3]) & 0xFF)
    ) % 1000000;

    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}
