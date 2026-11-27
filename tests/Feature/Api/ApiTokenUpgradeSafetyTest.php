<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;

/*
 * An install that updates the package but has not yet run the migration
 * adding api_tokens.kind / extension_id must still mint admin and mobile
 * tokens: those keys are only written when they carry a value.
 */

function upgradeSafetyUser(array $attrs = []): TestUser
{
    return TestUser::create(array_merge([
        'name' => 'Upgrade User',
        'email' => 'upgrade-'.uniqid().'@example.com',
        'password' => bcrypt('password123'),
    ], $attrs));
}

function dropApiTokenKindColumns(): void
{
    Escalated::schema()->table(Escalated::table('api_tokens'), function (Blueprint $blueprint) {
        $blueprint->dropColumn(['kind', 'extension_id']);
    });
}

it('leaves kind and extension_id out of the insert when they are null', function () {
    $user = upgradeSafetyUser();
    $inserts = [];

    Escalated::db()->listen(function ($query) use (&$inserts) {
        if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, Escalated::table('api_tokens'))) {
            $inserts[] = $query->sql;
        }
    });

    ApiToken::createToken($user, 'Plain token', ['agent']);

    expect($inserts)->toHaveCount(1)
        ->and($inserts[0])->not->toContain('kind')
        ->and($inserts[0])->not->toContain('extension_id');
});

it('still writes kind and extension_id when they are given', function () {
    $user = upgradeSafetyUser();

    $token = ApiToken::createToken($user, 'Browser extension', ['agent'], null, 'browser_extension', str_repeat('a', 32))['token'];

    expect($token->fresh()->kind)->toBe('browser_extension')
        ->and($token->fresh()->extension_id)->toBe(str_repeat('a', 32));
});

it('mints admin and mobile tokens against a table without the new columns', function () {
    if (Escalated::db()->getDriverName() === 'mysql' || Escalated::db()->getDriverName() === 'mariadb') {
        // MySQL DDL commits the RefreshDatabase transaction; the insert
        // payload test above covers this driver.
        $this->markTestSkipped('DDL is not transactional on MySQL.');
    }

    dropApiTokenKindColumns();

    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    $admin = upgradeSafetyUser(['is_admin' => true, 'is_agent' => true]);
    $agent = upgradeSafetyUser(['is_agent' => true]);

    $this->actingAs($admin)
        ->post('/support/admin/api-tokens', [
            'name' => 'Desktop App',
            'user_id' => $agent->getKey(),
            'abilities' => ['agent'],
        ])
        ->assertRedirect()
        ->assertSessionHas('plain_text_token');

    upgradeSafetyUser(['email' => 'mobile-upgrade@example.com']);

    $this->postJson('/support/api/v1/mobile/auth/login', [
        'email' => 'mobile-upgrade@example.com',
        'password' => 'password123',
    ])->assertOk()->assertJsonStructure(['token']);

    expect(ApiToken::count())->toBe(2);
});
