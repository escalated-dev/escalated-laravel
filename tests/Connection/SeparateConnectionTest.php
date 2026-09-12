<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Schema;

/**
 * Two in-memory SQLite databases share no schema, so a query that resolves the
 * wrong connection cannot quietly succeed — it fails with "no such table".
 * That is what makes these assertions worth anything.
 */
it('migrates every escalated table onto the configured connection', function () {
    $tables = collect(glob(__DIR__.'/../../database/migrations/*_create_escalated_*_table.php'))
        ->map(fn (string $path) => preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_create_(escalated_[a-z_]+)_table\.php$/', '$1', basename($path)))
        ->filter(fn (string $table) => str_starts_with($table, 'escalated_'))
        ->values();

    expect($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        expect(Schema::connection('escalated')->hasTable($table))
            ->toBeTrue("[{$table}] should have been created on the escalated connection");
    }
});

it('creates none of them on the host default connection', function () {
    expect(Schema::connection('testing')->hasTable('escalated_tickets'))->toBeFalse()
        ->and(Schema::connection('testing')->hasTable('escalated_replies'))->toBeFalse()
        ->and(Schema::connection('testing')->hasTable('escalated_departments'))->toBeFalse();
});

it('leaves the host users table on the host connection', function () {
    expect(Schema::connection('testing')->hasTable('users'))->toBeTrue()
        ->and(Schema::connection('escalated')->hasTable('users'))->toBeFalse();
});

it('resolves models against the configured connection', function () {
    expect((new Ticket)->getConnectionName())->toBe('escalated')
        ->and((new Reply)->getConnectionName())->toBe('escalated')
        ->and((new Department)->getConnectionName())->toBe('escalated');
});

it('writes and reads ticket data on the configured connection', function () {
    $user = $this->createTestUser();

    $ticket = Ticket::create([
        'reference' => 'ESC-CONN-1',
        'subject' => 'Stored elsewhere',
        'description' => 'Body',
        'requester_type' => $user::class,
        'requester_id' => $user->getKey(),
        'status' => 'open',
        'priority' => 'medium',
    ]);

    expect($ticket->getConnectionName())->toBe('escalated')
        ->and(Ticket::find($ticket->id)->subject)->toBe('Stored elsewhere');

    // The row is really over there, not merely reachable through the model.
    $row = Escalated::db()->table(Escalated::table('tickets'))->where('reference', 'ESC-CONN-1')->first();
    expect($row)->not->toBeNull()->and($row->subject)->toBe('Stored elsewhere');
});

it('resolves a relation to a host user across the connection boundary', function () {
    $user = $this->createTestUser(['name' => 'Across The Divide']);

    $ticket = Ticket::create([
        'reference' => 'ESC-CONN-2',
        'subject' => 'Cross-connection requester',
        'description' => 'Body',
        'requester_type' => $user::class,
        'requester_id' => $user->getKey(),
        'status' => 'open',
        'priority' => 'medium',
    ]);

    // The ticket lives on `escalated`, its requester on `testing`. Escalated
    // stores host user ids as plain unconstrained columns precisely so the two
    // can sit on different connections.
    expect($ticket->fresh()->requester->name)->toBe('Across The Divide');
});

it('keeps relations between escalated models on the escalated connection', function () {
    $user = $this->createTestUser();

    $ticket = Ticket::create([
        'reference' => 'ESC-CONN-3',
        'subject' => 'With replies',
        'description' => 'Body',
        'requester_type' => $user::class,
        'requester_id' => $user->getKey(),
        'status' => 'open',
        'priority' => 'medium',
    ]);

    $ticket->replies()->create([
        'author_type' => $user::class,
        'author_id' => $user->getKey(),
        'body' => 'A reply',
    ]);

    expect($ticket->fresh()->replies)->toHaveCount(1)
        ->and($ticket->replies()->first()->getConnectionName())->toBe('escalated');
});

it('points the helper accessors at the right databases', function () {
    expect(Escalated::connection())->toBe('escalated')
        ->and(Escalated::db()->getName())->toBe('escalated')
        ->and(Escalated::schema()->getConnection()->getName())->toBe('escalated')
        // The users table belongs to the host, so its schema lookups must not
        // follow Escalated anywhere.
        ->and(Escalated::userSchema()->getConnection()->getName())->toBe('testing')
        ->and(Escalated::userSchema()->hasColumn('users', 'name'))->toBeTrue();
});

it('runs transactions on the connection the writes land on', function () {
    $user = $this->createTestUser();

    Escalated::db()->transaction(function () use ($user) {
        Ticket::create([
            'reference' => 'ESC-CONN-TX',
            'subject' => 'Transacted',
            'description' => 'Body',
            'requester_type' => $user::class,
            'requester_id' => $user->getKey(),
            'status' => 'open',
            'priority' => 'medium',
        ]);
    });

    expect(Ticket::where('reference', 'ESC-CONN-TX')->exists())->toBeTrue();
});

/**
 * The four relations where an Escalated pivot joins the HOST's users table.
 * These are the shape a configurable connection genuinely breaks: Eloquent
 * resolves a belongsToMany with one JOIN, and no database can join across two
 * connections. They are resolved in two steps instead.
 */
it('reads ticket followers across the connection boundary', function () {
    $user = $this->createTestUser(['name' => 'Follower One']);
    $other = $this->createTestUser(['email' => 'two@example.com', 'name' => 'Follower Two']);

    $ticket = Ticket::create([
        'reference' => 'ESC-CONN-FOLLOW',
        'subject' => 'Followed',
        'description' => 'Body',
        'requester_type' => $user::class,
        'requester_id' => $user->getKey(),
        'status' => 'open',
        'priority' => 'medium',
    ]);

    $ticket->follow($user->getKey());
    $ticket->follow($other->getKey());

    expect($ticket->followers()->count())->toBe(2)
        ->and($ticket->isFollowedBy($user->getKey()))->toBeTrue()
        ->and($ticket->fresh()->followers->pluck('name')->sort()->values()->all())
        ->toBe(['Follower One', 'Follower Two']);

    $ticket->unfollow($other->getKey());
    expect($ticket->fresh()->followers)->toHaveCount(1);
});

it('eager loads host-user pivots without an n+1 or a cross-connection join', function () {
    $user = $this->createTestUser(['name' => 'Shared Follower']);

    foreach (['A', 'B', 'C'] as $suffix) {
        Ticket::create([
            'reference' => "ESC-CONN-EAGER-{$suffix}",
            'subject' => "Eager {$suffix}",
            'description' => 'Body',
            'requester_type' => $user::class,
            'requester_id' => $user->getKey(),
            'status' => 'open',
            'priority' => 'medium',
        ])->follow($user->getKey());
    }

    $tickets = Ticket::where('reference', 'like', 'ESC-CONN-EAGER-%')->with('followers')->get();

    expect($tickets)->toHaveCount(3);

    foreach ($tickets as $ticket) {
        expect($ticket->relationLoaded('followers'))->toBeTrue()
            ->and($ticket->followers)->toHaveCount(1)
            ->and($ticket->followers->first()->name)->toBe('Shared Follower');
    }
});

it('counts a host-user pivot through the pivot table alone', function () {
    $user = $this->createTestUser();
    $department = Department::create(['name' => 'Support', 'slug' => 'support']);
    $department->agents()->attach($user->getKey());

    // withCount() normally compiles a correlated subquery that joins the
    // related table; across connections that cannot compile at all.
    $loaded = Department::withCount('agents')->find($department->id);

    expect($loaded->agents_count)->toBe(1)
        ->and($department->agents()->count())->toBe(1)
        ->and($department->fresh()->agents->first()->getKey())->toBe($user->getKey());
});

it('carries pivot columns across the boundary', function () {
    $user = $this->createTestUser();
    $skill = Skill::create(['name' => 'Billing', 'slug' => 'billing']);

    $skill->agents()->sync([$user->getKey() => ['proficiency' => 4]]);

    $agent = $skill->fresh()->agents->first();

    expect($agent->getKey())->toBe($user->getKey())
        ->and((int) $agent->pivot->proficiency)->toBe(4);
});

it('drops a pivot row whose host user has since been deleted', function () {
    $kept = $this->createTestUser(['name' => 'Kept']);
    $removed = $this->createTestUser(['email' => 'gone@example.com', 'name' => 'Gone']);

    $ticket = Ticket::create([
        'reference' => 'ESC-CONN-ORPHAN',
        'subject' => 'Orphaned follower',
        'description' => 'Body',
        'requester_type' => $kept::class,
        'requester_id' => $kept->getKey(),
        'status' => 'open',
        'priority' => 'medium',
    ]);

    $ticket->follow($kept->getKey());
    $ticket->follow($removed->getKey());

    // No cross-connection foreign key can cascade this away, so the pivot row
    // outlives the user. It must be skipped, not hydrated as a hole.
    $removed->delete();

    expect($ticket->fresh()->followers->pluck('name')->all())->toBe(['Kept']);
});
