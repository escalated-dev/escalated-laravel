<?php

use Escalated\Laravel\Bridge\ContextHandler;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\MentionService;
use Escalated\Laravel\Services\TicketService;
use Escalated\Laravel\Tests\Fixtures\SplitNameUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/*
 * Issue #316: a host whose users table has first_name / last_name and a role
 * column, but no `name`, `is_agent` or `is_admin`. Display names fall back to
 * email and staff comes from the host's gates.
 */

beforeEach(function () {
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['name', 'is_agent', 'is_admin']);
    });
    Schema::table('users', function (Blueprint $table) {
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('role')->default('customer');
    });
    Escalated::flushColumnCache();
    config(['escalated.user_model' => SplitNameUser::class]);

    Gate::define('escalated-agent', fn ($user) => in_array($user->role, ['agent', 'admin'], true));
    Gate::define('escalated-admin', fn ($user) => $user->role === 'admin');

    $make = fn (string $first, string $email, string $role) => SplitNameUser::create([
        'first_name' => $first, 'last_name' => 'Example', 'email' => $email,
        'password' => bcrypt('x'), 'role' => $role,
    ]);
    $this->admin = $make('Ada', 'ada@example.com', 'admin');
    $this->agent = $make('Ann', 'ann@example.com', 'agent');
    $this->customer = $make('Cal', 'cal@example.com', 'customer');
});

afterEach(function () {
    Escalated::flushColumnCache();
});

/**
 * Runs the callback and fails if it queried the users table for a column
 * this host does not have. SQLite reads an unknown double-quoted column as a
 * string literal instead of failing, so the suite checks the SQL itself
 * (MySQL and PostgreSQL in CI fail on the query too).
 */
function withoutMissingUserColumns(callable $callback): mixed
{
    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = $query->sql;
    });

    $result = $callback();

    $bad = array_filter($sql, fn (string $statement) => preg_match('/\b(from|update|into)\s+["`]?users["`]?/i', $statement)
        && preg_match('/["`](name|is_agent|is_admin)["`]/', $statement));
    expect(array_values($bad))->toBe([]);

    return $result;
}

it('saves a staff reply that contains an email address', function () {
    $ticket = Ticket::factory()->create();

    $reply = withoutMissingUserColumns(
        fn () => app(TicketService::class)->reply($ticket, $this->agent, 'Please email me at jo@example.com.'),
    );

    expect($reply->exists)->toBeTrue();
});

it('matches mentions against email when there is no name column', function () {
    $ids = withoutMissingUserColumns(
        fn () => app(MentionService::class)->extractMentions('Over to you @{ann@example.com}.'),
    );

    expect($ids)->toBe([$this->agent->id]);
});

it('suggests agents through the staff gates', function () {
    $suggestions = withoutMissingUserColumns(
        fn () => app(MentionService::class)->getAgentSuggestions('example.com'),
    );

    expect($suggestions->pluck('id')->sort()->values()->all())->toBe([$this->admin->id, $this->agent->id])
        ->and($suggestions->firstWhere('id', $this->agent->id)['name'])->toBe('ann@example.com');
});

it('opens the audit log', function () {
    withoutMissingUserColumns(fn () => $this->actingAs($this->admin)
        ->get(route('escalated.admin.audit-log'))
        ->assertOk());
});

it('lists only staff on the skill form and rejects a customer as a skill agent', function () {
    $this->actingAs($this->admin)
        ->get(route('escalated.admin.skills.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('availableAgents', fn ($agents) => collect($agents)->pluck('id')->sort()->values()->all()
                === [$this->admin->id, $this->agent->id]));

    $this->actingAs($this->admin)
        ->post(route('escalated.admin.skills.store'), [
            'name' => 'Billing',
            'agents' => [['user_id' => $this->customer->id, 'proficiency' => 3]],
        ])
        ->assertSessionHasErrors('agents.0.user_id');

    $this->actingAs($this->admin)
        ->post(route('escalated.admin.skills.store'), [
            'name' => 'Billing',
            'agents' => [['user_id' => $this->agent->id, 'proficiency' => 3]],
        ])
        ->assertSessionHasNoErrors();
});

it('lists users with access from the gates and read-only switches', function () {
    $this->actingAs($this->admin)
        ->get(route('escalated.admin.users.index', ['search' => 'ann']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roleToggles', false)
            ->has('notice')
            ->has('users.data', 1)
            ->where('users.data.0.id', $this->agent->id)
            ->where('users.data.0.is_agent', true)
            ->where('users.data.0.is_admin', false));
});

it('refuses to write role columns the host does not have', function () {
    $this->actingAs($this->admin)
        ->patch(route('escalated.admin.users.role', $this->customer->id), ['role' => 'agent', 'value' => true])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($this->customer->fresh()->role)->toBe('customer');
});

it('gives plugins only staff in ctx.agents.all', function () {
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');
    $agents = $handler->handle('ctx.agents.all', []);

    expect(collect($agents)->pluck('id')->sort()->values()->all())->toBe([$this->admin->id, $this->agent->id])
        ->and(collect($agents)->firstWhere('id', $this->agent->id))->toMatchArray(['email' => 'ann@example.com']);
});
