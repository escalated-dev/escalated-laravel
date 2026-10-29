<?php

use Escalated\Laravel\Database\Seeders\PermissionSeeder;
use Escalated\Laravel\Http\Middleware\CheckPermission;
use Escalated\Laravel\Http\Middleware\EnforceRoutePermissions;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Permission;
use Escalated\Laravel\Models\Role;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\RoutePermissions;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * ADR 2026-10-29-configurable-access-features: roles & permissions is an
 * install-level switch (escalated.access.roles_permissions). Off, the host
 * gates decide everything and the roles screens answer 404. On, every admin,
 * agent and staff API route also needs its permission from the user's roles.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    (new PermissionSeeder)->run();
});

function rpUser(string $kind, ?string $role = null): TestUser
{
    $user = TestUser::create([
        'name' => ucfirst($kind),
        'email' => $kind.'-'.Str::random(8).'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
        'is_admin' => $kind === 'admin',
    ]);

    if ($role !== null) {
        Role::where('slug', $role)->firstOrFail()->users()->attach($user->getKey());
    }

    return $user;
}

function rpRoleWith(array $slugs): Role
{
    $role = Role::create(['name' => 'Custom '.Str::random(6)]);
    $role->permissions()->sync(
        Permission::whereIn('slug', $slugs)->pluck('id')->all()
    );

    return $role;
}

function rpEnforce(): void
{
    config(['escalated.access.roles_permissions' => true]);
}

// --- Switch off (the default) ------------------------------------------------

it('is off by default', function () {
    expect(config('escalated.access.roles_permissions'))->toBeFalse();
});

it('hides the roles screens while the switch is off', function () {
    $admin = rpUser('admin', 'admin');

    $this->actingAs($admin)->get(route('escalated.admin.roles.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('escalated.admin.roles.create'))->assertNotFound();
    $this->actingAs($admin)->post(route('escalated.admin.roles.store'), ['name' => 'X'])->assertNotFound();
});

it('lets the gates alone decide while the switch is off', function () {
    $agent = rpUser('agent', 'light_agent');
    $other = rpUser('agent');
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.assign', $ticket->reference), ['agent_id' => $other->getKey()])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($ticket->fresh()->assigned_to)->toBe($other->getKey());

    $admin = rpUser('admin');
    $this->actingAs($admin)->get(route('escalated.admin.departments.index'))->assertOk();
});

it('shares the switch and a wildcard permission set for staff while off', function () {
    $admin = rpUser('admin');

    $this->actingAs($admin)->get(route('escalated.admin.departments.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.features.roles_permissions', false)
            ->where('escalated.permissions', ['*']));
});

it('does not apply the newsletter permissions while the switch is off', function () {
    Route::middleware(['web', CheckPermission::class.':newsletters.manage'])
        ->get('/__rp/newsletters', fn () => 'ok');

    $this->actingAs(rpUser('admin'))->get('/__rp/newsletters')->assertOk();

    rpEnforce();
    $this->actingAs(rpUser('admin'))->get('/__rp/newsletters')->assertForbidden();
    $this->actingAs(rpUser('admin', 'admin'))->get('/__rp/newsletters')->assertOk();
});

// --- Switch on ---------------------------------------------------------------

it('shows the roles screens to a user with role permissions while on', function () {
    rpEnforce();
    $admin = rpUser('admin', 'admin');

    $this->actingAs($admin)->get(route('escalated.admin.roles.index'))->assertOk();
});

it('grants nothing to an admin without a role', function () {
    rpEnforce();
    $admin = rpUser('admin');

    $this->actingAs($admin)->get(route('escalated.admin.departments.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('escalated.admin.roles.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('escalated.admin.settings.update'), [])->assertForbidden();
});

it('lets an admin with the admin role through', function () {
    rpEnforce();
    $admin = rpUser('admin', 'admin');

    $this->actingAs($admin)->get(route('escalated.admin.departments.index'))->assertOk();
    $this->actingAs($admin)->get(route('escalated.admin.settings'))->assertOk();
});

it('checks admin reads and writes against separate permissions', function () {
    rpEnforce();
    $admin = rpUser('admin');
    rpRoleWith(['department.view'])->users()->attach($admin->getKey());

    $this->actingAs($admin)->get(route('escalated.admin.departments.index'))->assertOk();
    $this->actingAs($admin)->post(route('escalated.admin.departments.store'), ['name' => 'Billing'])->assertForbidden();
    $this->actingAs($admin)->get(route('escalated.admin.webhooks.index'))->assertForbidden();
});

it('shares the user\'s real permission slugs while on', function () {
    rpEnforce();
    $admin = rpUser('admin');
    rpRoleWith(['department.view'])->users()->attach($admin->getKey());

    $this->actingAs($admin)->get(route('escalated.admin.departments.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.features.roles_permissions', true)
            ->where('escalated.permissions', ['department.view']));
});

it('limits a light agent to viewing and notes', function () {
    rpEnforce();
    $agent = rpUser('agent', 'light_agent');
    $other = rpUser('agent');
    $ticket = Ticket::factory()->create();
    $ref = $ticket->reference;

    $this->actingAs($agent)->get(route('escalated.agent.tickets.show', $ref))->assertOk();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.note', $ref), ['body' => 'Internal'])->assertRedirect();

    $this->actingAs($agent)->post(route('escalated.agent.tickets.assign', $ref), ['agent_id' => $other->getKey()])->assertForbidden();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.priority', $ref), ['priority' => 'high'])->assertForbidden();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.status', $ref), ['status' => 'closed'])->assertForbidden();

    expect($ticket->fresh()->assigned_to)->toBeNull();
});

it('lets a full agent assign and change status', function () {
    rpEnforce();
    $agent = rpUser('agent', 'agent');
    $other = rpUser('agent');
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.assign', $ticket->reference), ['agent_id' => $other->getKey()])
        ->assertRedirect();

    expect($ticket->fresh()->assigned_to)->toBe($other->getKey());
});

it('needs ticket.close only to close, resolve or reopen', function () {
    rpEnforce();
    $agent = rpUser('agent');
    rpRoleWith(['ticket.view', 'ticket.edit'])->users()->attach($agent->getKey());
    $ticket = Ticket::factory()->create();
    $ref = $ticket->reference;

    $this->actingAs($agent)->post(route('escalated.agent.tickets.status', $ref), ['status' => 'closed'])->assertForbidden();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.status', $ref), ['status' => 'resolved'])->assertForbidden();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.status', $ref), ['status' => 'in_progress'])->assertRedirect();

    expect($ticket->fresh()->status->value)->toBe('in_progress');
});

it('checks each bulk action against its own permission', function () {
    rpEnforce();
    $agent = rpUser('agent', 'light_agent');
    $other = rpUser('agent');
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)->post(route('escalated.agent.tickets.bulk'), [
        'ticket_ids' => [$ticket->id],
        'action' => 'assign',
        'value' => $other->getKey(),
    ])->assertForbidden();

    $this->actingAs($agent)->post(route('escalated.agent.tickets.bulk'), [
        'ticket_ids' => [$ticket->id],
        'action' => 'delete',
    ])->assertForbidden();

    expect($ticket->fresh())->not->toBeNull()
        ->and($ticket->fresh()->assigned_to)->toBeNull();
});

it('checks the API token owner\'s permissions', function () {
    rpEnforce();
    $agent = rpUser('agent', 'light_agent');
    $other = rpUser('agent');
    $token = ApiToken::createToken($agent, 'Token', ['agent'])['plainTextToken'];
    $ticket = Ticket::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$token];

    $this->getJson('/support/api/v1/tickets/'.$ticket->reference, $headers)->assertOk();
    $this->postJson('/support/api/v1/tickets/'.$ticket->reference.'/assign', ['agent_id' => $other->getKey()], $headers)
        ->assertForbidden();
});

it('maps every admin, agent and staff API route to a permission', function () {
    $unmapped = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => is_string($name)
            && Str::is(['escalated.admin.*', 'escalated.agent.*', 'escalated.api.*'], $name)
            && ! Str::startsWith($name, 'escalated.api.mobile.'))
        ->reject(fn ($name) => RoutePermissions::isMapped($name))
        ->values()
        ->all();

    expect($unmapped)->toBe([]);
});

it('refuses a staff route that has no permission mapping', function () {
    rpEnforce();
    Route::middleware(['web', EnforceRoutePermissions::class])
        ->get('/__rp/unmapped', fn () => 'ok')
        ->name('escalated.admin.not-in-the-map');

    $this->actingAs(rpUser('admin', 'admin'))->get('/__rp/unmapped')->assertForbidden();
});

it('assigns a role from the command line', function () {
    $user = rpUser('admin');

    $this->artisan('escalated:roles:assign', ['user' => $user->email, 'role' => 'admin'])
        ->assertSuccessful();

    expect(CheckPermission::userPermissions($user->getKey()))->toContain('role.manage');

    $this->artisan('escalated:roles:assign', ['user' => 'nobody@example.com', 'role' => 'admin'])
        ->assertFailed();
    $this->artisan('escalated:roles:assign', ['user' => $user->email, 'role' => 'nope'])
        ->assertFailed();
});

it('lets a role manager set a user\'s roles while on', function () {
    rpEnforce();
    $admin = rpUser('admin', 'admin');
    $agent = rpUser('agent', 'agent');
    $light = Role::where('slug', 'light_agent')->first();

    $this->actingAs($admin)
        ->put(route('escalated.admin.users.roles', $agent->getKey()), ['role_ids' => [$light->id]])
        ->assertRedirect();

    expect(CheckPermission::userPermissions($agent->getKey()))->not->toContain('ticket.assign')
        ->and(CheckPermission::userPermissions($agent->getKey()))->toContain('reply.create_internal');

    $this->actingAs($admin)->get(route('escalated.admin.users.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->pluck('slug')->contains('light_agent'))
            ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $agent->getKey())['roles'] === ['light_agent']));
});

it('refuses role changes without role.manage and while off', function () {
    $agent = rpUser('agent', 'agent');
    $light = Role::where('slug', 'light_agent')->first();

    $this->actingAs(rpUser('admin', 'admin'))
        ->put(route('escalated.admin.users.roles', $agent->getKey()), ['role_ids' => [$light->id]])
        ->assertNotFound();

    rpEnforce();
    $admin = rpUser('admin');
    rpRoleWith(['user.view', 'user.manage'])->users()->attach($admin->getKey());

    $this->actingAs($admin)
        ->put(route('escalated.admin.users.roles', $agent->getKey()), ['role_ids' => [$light->id]])
        ->assertForbidden();
});

it('does not let a role manager remove their own role management', function () {
    rpEnforce();
    $admin = rpUser('admin', 'admin');
    $light = Role::where('slug', 'light_agent')->first();

    $this->actingAs($admin)
        ->put(route('escalated.admin.users.roles', $admin->getKey()), ['role_ids' => [$light->id]])
        ->assertSessionHas('error');

    expect(CheckPermission::userPermissions($admin->getKey()))->toContain('role.manage');
});
