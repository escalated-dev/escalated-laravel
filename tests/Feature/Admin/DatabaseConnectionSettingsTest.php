<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Support\ConnectionInspector;
use Escalated\Laravel\Support\ConnectionStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

/**
 * The admin screen for the database Escalated's tables live on.
 *
 * The risky parts are what these pin. Switching to an unmigrated database does
 * not error -- it renders a panel with no tickets, no departments and no
 * settings, which reads exactly like data loss -- so the screen has to refuse
 * that rather than perform it. And the choice cannot be stored in the database
 * it selects, because reading it back would mean already knowing it.
 */
beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    File::delete(ConnectionStore::path());
    ConnectionStore::flush();
    Escalated::useConnection();
    config()->set('escalated.connection', null);

    config()->set('database.connections.support', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
});

afterEach(function () {
    File::delete(ConnectionStore::path());
    ConnectionStore::flush();
    Escalated::useConnection();
});

it('renders every configured connection with its state', function () {
    $this->actingAs($this->createAdmin());

    $this->get(route('escalated.admin.settings.database'))
        ->assertOk();

    $connections = collect(app(ConnectionInspector::class)->all());

    expect($connections->pluck('name'))->toContain('testing', 'support')
        ->and($connections->firstWhere('name', 'testing')['is_current'])->toBeTrue()
        ->and($connections->firstWhere('name', 'testing')['migrated'])->toBeTrue();
});

it('refuses a connection that has no escalated tables', function () {
    $this->actingAs($this->createAdmin());

    // `support` is configured but never migrated. Switching would empty the
    // panel without erroring, which is the single worst outcome here.
    $this->post(route('escalated.admin.settings.database.update'), ['connection' => 'support'])
        ->assertSessionHasErrors('connection');

    expect(ConnectionStore::get())->toBeNull()
        ->and(Escalated::connection())->toBeNull();
});

it('refuses a connection the host never configured', function () {
    $this->actingAs($this->createAdmin());

    $this->post(route('escalated.admin.settings.database.update'), ['connection' => 'nonexistent'])
        ->assertSessionHasErrors('connection');

    expect(ConnectionStore::get())->toBeNull();
});

it('stores the choice outside the database it selects', function () {
    $this->actingAs($this->createAdmin());

    // The already-migrated test connection is a legitimate target, which lets
    // this assert the storage location without a second migrated database.
    $this->post(route('escalated.admin.settings.database.update'), ['connection' => 'testing'])
        ->assertSessionHas('success');

    expect(File::exists(ConnectionStore::path()))->toBeTrue()
        ->and(ConnectionStore::get())->toBe('testing');

    // The point of the file: it is readable with no working database at all.
    expect(require ConnectionStore::path())->toBe('testing');
});

it('clears the choice back to the host default', function () {
    $this->actingAs($this->createAdmin());
    ConnectionStore::put('testing');

    $this->post(route('escalated.admin.settings.database.update'), ['connection' => ''])
        ->assertSessionHas('success');

    expect(ConnectionStore::get())->toBeNull()
        ->and(File::exists(ConnectionStore::path()))->toBeFalse()
        ->and(Escalated::connection())->toBeNull();
});

it('treats a config-pinned connection as read-only', function () {
    config()->set('escalated.connection', 'testing');
    $this->actingAs($this->createAdmin());

    expect(Escalated::connectionIsPinnedByConfig())->toBeTrue();

    $this->post(route('escalated.admin.settings.database.update'), ['connection' => ''])
        ->assertSessionHas('error');

    // Config is deployed infrastructure. The screen must not be able to
    // quietly disagree with it.
    expect(Escalated::connection())->toBe('testing');
});

it('lets config outrank a stored choice that disagrees with it', function () {
    ConnectionStore::put('support');
    config()->set('escalated.connection', 'testing');

    expect(Escalated::connection())->toBe('testing');
});

it('falls back to the stored choice when config says nothing', function () {
    ConnectionStore::put('support');
    config()->set('escalated.connection', null);

    expect(Escalated::connection())->toBe('support');
});

it('ignores a stored value that is not a usable connection name', function () {
    File::ensureDirectoryExists(dirname(ConnectionStore::path()));
    File::put(ConnectionStore::path(), "<?php\n\nreturn 'not a name; DROP TABLE';\n");
    ConnectionStore::flush();

    expect(ConnectionStore::get())->toBeNull();
});

it('probes a connection without changing anything', function () {
    $this->actingAs($this->createAdmin());

    $response = $this->postJson(route('escalated.admin.settings.database.test'), ['connection' => 'support'])
        ->assertOk();

    expect($response->json('name'))->toBe('support')
        ->and($response->json('reachable'))->toBeTrue()
        ->and($response->json('migrated'))->toBeFalse();

    expect(Escalated::connection())->toBeNull();
});

it('keeps the screen behind the admin gate', function () {
    $this->actingAs($this->createTestUser());

    $this->get(route('escalated.admin.settings.database'))->assertForbidden();
    $this->post(route('escalated.admin.settings.database.update'), ['connection' => ''])->assertForbidden();
});

it('honours a runtime override above everything else', function () {
    config()->set('escalated.connection', 'testing');
    ConnectionStore::put('support');

    $previous = Escalated::useConnection('support');
    expect(Escalated::connection())->toBe('support');

    Escalated::useConnection($previous);
    expect(Escalated::connection())->toBe('testing');
});
