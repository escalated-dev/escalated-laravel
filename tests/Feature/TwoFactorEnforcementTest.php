<?php

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TwoFactor;
use Escalated\Laravel\Support\TwoFactorSession;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;

/*
 * ADR 2026-10-29-configurable-access-features: two-factor is an install-level
 * switch (escalated.access.two_factor). Off, enrolment routes answer 404 and
 * nobody is challenged. On, a staff user with a confirmed enrolment must pass
 * the Auth/TwoFactorChallenge page before any admin or agent route, and an
 * admin can require every staff user to enrol.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    RateLimiter::clear('escalated-two-factor');
});

function tfUser(bool $admin = false): TestUser
{
    return TestUser::create([
        'name' => 'Staff',
        'email' => 'staff-'.Str::random(8).'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
        'is_admin' => $admin,
    ]);
}

function tfEnrol(TestUser $user, array $recovery = ['AAAA1111-BBBB2222', 'CCCC3333-DDDD4444']): string
{
    $secret = (new Google2FA)->generateSecretKey(32);

    TwoFactor::create([
        'user_id' => $user->getKey(),
        'secret' => $secret,
        'recovery_codes' => $recovery,
        'confirmed_at' => now(),
    ]);

    return $secret;
}

function tfCode(string $secret, int $offsetSteps = 0): string
{
    $g = new Google2FA;

    return $g->oathTotp($secret, $g->getTimestamp() + $offsetSteps);
}

function tfOn(): void
{
    config(['escalated.access.two_factor' => true]);
}

// --- Off (default) -----------------------------------------------------------

it('is off by default', function () {
    expect(config('escalated.access.two_factor'))->toBeFalse();
});

it('does not challenge and hides the two-factor routes while off', function () {
    $user = tfUser();
    tfEnrol($user);

    $this->actingAs($user)->get(route('escalated.agent.dashboard'))->assertOk();
    $this->actingAs($user)->get(route('escalated.admin.two-factor.index'))->assertNotFound();
    $this->actingAs($user)->post(route('escalated.admin.two-factor.setup'))->assertNotFound();
    $this->actingAs($user)->get('/support/two-factor/challenge')->assertNotFound();
    $this->actingAs($user)->post('/support/two-factor/challenge', ['code' => '000000'])->assertNotFound();
});

it('shares the switch with the frontend', function () {
    $admin = tfUser(true);

    $this->actingAs($admin)->get(route('escalated.agent.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('escalated.features.two_factor', false));

    tfOn();
    $this->actingAs($admin)->get(route('escalated.agent.dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.features.two_factor', true)
            ->where('escalated.two_factor_required', false));
});

// --- Challenge ---------------------------------------------------------------

it('sends an enrolled user to the challenge from agent and admin pages', function () {
    tfOn();
    $user = tfUser(true);
    tfEnrol($user);

    $this->actingAs($user)->get(route('escalated.agent.dashboard'))
        ->assertRedirect(route('escalated.two-factor.challenge'));
    $this->actingAs($user)->get(route('escalated.admin.departments.index'))
        ->assertRedirect(route('escalated.two-factor.challenge'));
    $this->actingAs($user)->getJson(route('escalated.agent.tickets.index'))
        ->assertForbidden()
        ->assertJson(['two_factor_required' => true]);
});

it('does not let an unchallenged session switch two-factor off', function () {
    tfOn();
    $user = tfUser();
    tfEnrol($user);

    $this->actingAs($user)->post(route('escalated.admin.two-factor.disable'))
        ->assertRedirect(route('escalated.two-factor.challenge'));

    expect(TwoFactor::where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('renders the shared challenge page', function () {
    tfOn();
    $user = tfUser();
    tfEnrol($user);

    $this->actingAs($user)->get(route('escalated.two-factor.challenge'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Escalated/Auth/TwoFactorChallenge', false)
            ->where('action', route('escalated.two-factor.verify'))
            ->has('error'));
});

it('accepts a valid code and returns to the page asked for', function () {
    tfOn();
    $user = tfUser();
    $secret = tfEnrol($user);
    $ticket = Ticket::factory()->create();
    $target = route('escalated.agent.tickets.show', $ticket->reference);

    $this->actingAs($user)->get($target)->assertRedirect(route('escalated.two-factor.challenge'));

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => tfCode($secret)])
        ->assertRedirect($target);

    $this->actingAs($user)->get($target)->assertOk();
});

it('refuses a wrong code', function () {
    tfOn();
    $user = tfUser();
    $secret = tfEnrol($user);
    $wrong = str_pad((string) ((((int) tfCode($secret)) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => $wrong])
        ->assertSessionHasErrors('code');

    $this->actingAs($user)->get(route('escalated.agent.dashboard'))
        ->assertRedirect(route('escalated.two-factor.challenge'));
});

it('refuses a code that was already used', function () {
    tfOn();
    $user = tfUser();
    $secret = tfEnrol($user);
    $code = tfCode($secret);

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => $code])->assertSessionHasNoErrors();

    event(new Login('web', $user, false));
    $this->flushSession();

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');
});

it('accepts a recovery code once', function () {
    tfOn();
    $user = tfUser();
    tfEnrol($user, ['AAAA1111-BBBB2222', 'CCCC3333-DDDD4444']);

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['recovery_code' => 'aaaa1111-bbbb2222'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($user)->get(route('escalated.agent.dashboard'))->assertOk();

    $this->flushSession();
    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['recovery_code' => 'AAAA1111-BBBB2222'])
        ->assertSessionHasErrors('recovery_code');

    expect(TwoFactor::where('user_id', $user->getKey())->first()->recovery_codes)->toBe(['CCCC3333-DDDD4444']);
});

it('throttles the verify route', function () {
    tfOn();
    $user = tfUser();
    tfEnrol($user);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => '000000']);
    }

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => '000000'])
        ->assertStatus(429);
});

it('challenges again after a new login', function () {
    tfOn();
    $user = tfUser();
    $secret = tfEnrol($user);

    $this->actingAs($user)->post(route('escalated.two-factor.verify'), ['code' => tfCode($secret)]);
    $this->actingAs($user)->get(route('escalated.agent.dashboard'))->assertOk();

    event(new Login('web', $user, false));

    $this->actingAs($user)->get(route('escalated.agent.dashboard'))
        ->assertRedirect(route('escalated.two-factor.challenge'));
});

it('does not pass a session for one user on to another', function () {
    tfOn();
    $first = tfUser();
    $secret = tfEnrol($first);
    $second = tfUser();
    tfEnrol($second);

    $this->actingAs($first)->post(route('escalated.two-factor.verify'), ['code' => tfCode($secret)]);

    $this->actingAs($second)->get(route('escalated.agent.dashboard'))
        ->assertRedirect(route('escalated.two-factor.challenge'));
});

// --- Enrolment ---------------------------------------------------------------

it('lets an agent who is not an admin enrol', function () {
    tfOn();
    $agent = tfUser();
    $g = new Google2FA;

    $this->actingAs($agent)->get(route('escalated.admin.two-factor.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Escalated/Admin/Settings/TwoFactor', false)
            ->where('enabled', false)
            ->where('required', false));

    $this->actingAs($agent)->post(route('escalated.admin.two-factor.setup'))->assertSessionHas('two_factor_setup');

    $secret = TwoFactor::where('user_id', $agent->getKey())->first()->secret;
    expect(strlen($secret))->toBeGreaterThanOrEqual(32);

    $this->actingAs($agent)->post(route('escalated.admin.two-factor.confirm'), ['code' => tfCode($secret)])
        ->assertSessionHas('two_factor_confirmed');

    // Confirming proves the second factor for this session.
    $this->actingAs($agent)->get(route('escalated.agent.dashboard'))->assertOk();
});

// --- Required ----------------------------------------------------------------

it('sends staff without an enrolment to enrol when two-factor is required', function () {
    tfOn();
    config(['escalated.two_factor.required' => true]);
    $agent = tfUser();

    $this->actingAs($agent)->get(route('escalated.agent.dashboard'))
        ->assertRedirect(route('escalated.admin.two-factor.index'));
    $this->actingAs($agent)->get(route('escalated.admin.two-factor.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('required', true));
    $this->actingAs($agent)->post(route('escalated.admin.two-factor.setup'))->assertSessionHas('two_factor_setup');
});

it('lets an admin require two-factor at runtime', function () {
    tfOn();
    $admin = tfUser(true);

    $this->actingAs($admin)->get(route('escalated.agent.dashboard'))->assertOk();

    EscalatedSettings::set('two_factor_required', '1');

    $this->actingAs($admin)->get(route('escalated.agent.dashboard'))
        ->assertRedirect(route('escalated.admin.two-factor.index'));
});

it('saves the requirement from the two-factor page on its own', function () {
    // Admin/Settings/TwoFactor posts only this key to the settings route.
    tfOn();
    $admin = tfUser(true);
    EscalatedSettings::set('ticket_reference_prefix', 'KEEP');

    $this->actingAs($admin)
        ->post(route('escalated.admin.settings.update'), ['two_factor_required' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(EscalatedSettings::getBool('two_factor_required', false))->toBeTrue()
        ->and(EscalatedSettings::get('ticket_reference_prefix'))->toBe('KEEP');

    // Once required, the admin must enrol before reaching any other screen.
    $this->actingAs($admin)
        ->post(route('escalated.admin.settings.update'), ['two_factor_required' => false])
        ->assertRedirect(route('escalated.admin.two-factor.index'));
    expect(EscalatedSettings::getBool('two_factor_required', false))->toBeTrue();

    tfEnrol($admin);
    $this->actingAs($admin)
        ->withSession([TwoFactorSession::KEY => (string) $admin->getKey()])
        ->post(route('escalated.admin.settings.update'), ['two_factor_required' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(EscalatedSettings::getBool('two_factor_required', true))->toBeFalse();
});

it('still validates the settings a partial save sends', function () {
    $admin = tfUser(true);

    $this->actingAs($admin)
        ->post(route('escalated.admin.settings.update'), ['ticket_reference_prefix' => ''])
        ->assertSessionHasErrors('ticket_reference_prefix');
});

it('ignores the requirement while two-factor is off', function () {
    config(['escalated.two_factor.required' => true]);

    $this->actingAs(tfUser())->get(route('escalated.agent.dashboard'))->assertOk();
});

// --- Reset -------------------------------------------------------------------

it('resets a user\'s enrolment from the command line', function () {
    $user = tfUser();
    tfEnrol($user);

    $this->artisan('escalated:two-factor:reset', ['user' => $user->email])->assertSuccessful();

    expect(TwoFactor::where('user_id', $user->getKey())->exists())->toBeFalse();
});
