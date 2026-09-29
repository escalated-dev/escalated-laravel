<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketSubjectLink;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\UnconfiguredTenantResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

beforeEach(function () {
    Event::fake([
        Events\TicketCreated::class,
        Events\TicketUpdated::class,
        Events\ReplyCreated::class,
        Events\InternalNoteAdded::class,
    ]);
    $this->agentA = $this->createAgent();
    $this->agentB = $this->createAgent(['email' => 'tenant-b@example.test']);
    $resolver = new class extends UnconfiguredTenantResolver
    {
        public array $members = [];

        public function canAccess(Model $user, string $tenantId): bool
        {
            return in_array($user->getKey(), $this->members[$tenantId] ?? [], true);
        }

        public function canReference(Model $model, string $tenantId): bool
        {
            return $this->canAccess($model, $tenantId);
        }

        public function scope(Builder $query, string $tenantId): void
        {
            $query->whereKey($this->members[$tenantId] ?? []);
        }
    };
    $resolver->members = ['merchant-a' => [$this->agentA->id], 'merchant-b' => [$this->agentB->id]];
    app()->instance(TenantResolver::class, $resolver);
    config(['escalated.tenancy.enabled' => true]);
    $this->context = app(TenantContext::class);
    $this->ticketA = $this->context->run('merchant-a', fn () => Ticket::factory()->create(['assigned_to' => $this->agentA->id]));
    $this->ticketB = $this->context->run('merchant-b', fn () => Ticket::factory()->create(['assigned_to' => $this->agentB->id]));
});

it('fails closed without a current tenant and restores nested trusted contexts', function () {
    expect(fn () => Ticket::count())->toThrow(AuthorizationException::class);
    $this->context->run('merchant-a', function () {
        expect(Ticket::pluck('id')->all())->toBe([$this->ticketA->id]);
        $this->context->run('merchant-b', fn () => expect(Ticket::pluck('id')->all())->toBe([$this->ticketB->id]));
        expect($this->context->current())->toBe('merchant-a');
    });
    expect($this->context->current())->toBeNull();
});

it('cannot remove the tenant boundary with optional global scopes or queue restoration', function () {
    $this->context->run('merchant-a', function () {
        expect(Ticket::withoutGlobalScopes()->pluck('id')->all())->toBe([$this->ticketA->id])
            ->and(Ticket::withoutGlobalScopes()->whereKey($this->ticketB->id)->orWhere('id', $this->ticketA->id)->pluck('id')->all())->toBe([$this->ticketA->id])
            ->and((new Ticket)->newQueryForRestoration([$this->ticketB->id])->exists())->toBeFalse();
    });
});

it('rejects loaded foreign records on normal quiet and refresh paths', function (string $operation) {
    $this->context->run('merchant-a', function () use ($operation) {
        expect(fn () => match ($operation) {
            'quiet' => $this->ticketB->updateQuietly(['subject' => 'cross tenant']),
            'delete' => $this->ticketB->delete(),
            'refresh' => $this->ticketB->refresh(),
            'fresh' => $this->ticketB->fresh(),
            'increment' => $this->ticketB->increment('id'),
        })->toThrow(AuthorizationException::class);
    });
})->with(['quiet', 'delete', 'refresh', 'fresh', 'increment']);

it('rejects tenant reassignment and cross-tenant child and host references', function () {
    $this->context->run('merchant-a', function () {
        expect(fn () => $this->ticketA->updateQuietly(['tenant_id' => 'merchant-b']))->toThrow(AuthorizationException::class);
        expect(fn () => Ticket::whereKey($this->ticketA->id)->update(['tenant_id' => 'merchant-b']))->toThrow(AuthorizationException::class);
        expect(fn () => Ticket::whereKey($this->ticketA->id)->update(['assigned_to' => $this->agentB->id]))->toThrow(AuthorizationException::class);
        expect(fn () => Reply::create(['ticket_id' => $this->ticketB->id, 'body' => 'foreign']))->toThrow(AuthorizationException::class);
    });
});

it('scopes bulk writes and stamps bulk inserts while rejecting unsafe conflict operations', function () {
    $this->context->run('merchant-a', function () {
        Ticket::query()->update(['subject' => 'Only A']);
        expect(Ticket::whereKey($this->ticketA->id)->value('subject'))->toBe('Only A');
        Contact::insert([['email' => 'same@example.test', 'name' => 'A']]);
        expect(Contact::sole()->tenant_id)->toBe('merchant-a');
        expect(fn () => Contact::insert([['email' => 'wrong@example.test', 'tenant_id' => 'merchant-b']]))->toThrow(AuthorizationException::class);
        expect(fn () => Contact::upsert([['email' => 'same@example.test']], ['email']))->toThrow(AuthorizationException::class);
        expect(fn () => Contact::query()->truncate())->toThrow(AuthorizationException::class);
    });
    $this->context->run('merchant-b', function () {
        expect(Ticket::sole()->subject)->not->toBe('Only A');
        Contact::create(['email' => 'same@example.test', 'name' => 'B']);
        expect(Contact::sole()->name)->toBe('B');
    });
});

it('allows tenant-specific unique slugs and rejects a foreign package association on bulk update', function () {
    $department = $this->context->run('merchant-b', fn () => Department::create(['name' => 'Support B', 'slug' => 'support']));
    $this->context->run('merchant-a', function () use ($department) {
        Department::create(['name' => 'Support A', 'slug' => 'support']);
        expect(Department::sole()->name)->toBe('Support A');
        expect(fn () => Ticket::query()->update(['department_id' => $department->id]))->toThrow(AuthorizationException::class);
    });
});

it('stamps package pivots and rejects foreign IDs before detaching current members', function () {
    $foreign = $this->context->run('merchant-b', fn () => Tag::create(['name' => 'B', 'slug' => 'shared']));
    $this->context->run('merchant-a', function () use ($foreign) {
        $tag = Tag::create(['name' => 'A', 'slug' => 'shared']);
        $this->ticketA->tags()->attach($tag);
        expect($this->ticketA->tags()->sole()->pivot->tenant_id)->toBe('merchant-a');
        expect(fn () => $this->ticketA->tags()->sync([$foreign->id]))->toThrow(AuthorizationException::class);
        expect($this->ticketA->tags()->pluck('name')->all())->toBe(['A']);
        expect(fn () => $this->ticketA->tags()->updateExistingPivot($tag->id, ['tenant_id' => 'merchant-b']))->toThrow(AuthorizationException::class);
        expect(fn () => $this->ticketB->tags()->attach($tag))->toThrow(AuthorizationException::class);
    });
});

it('keeps host pivots tenant-scoped for reads eager counts existence sync and detach', function () {
    $this->context->run('merchant-a', fn () => $this->ticketA->followers()->attach($this->agentA));
    $this->context->run('merchant-b', fn () => $this->ticketB->followers()->attach($this->agentB));
    $this->context->run('merchant-a', function () {
        $ticket = Ticket::with('followers')->withCount('followers')->whereHas('followers')->sole();
        expect($ticket->followers->modelKeys())->toBe([$this->agentA->id])
            ->and($ticket->followers_count)->toBe(1)
            ->and($ticket->followers->sole()->pivot->tenant_id)->toBe('merchant-a');
        expect(fn () => $ticket->followers()->sync([$this->agentB->id]))->toThrow(AuthorizationException::class);
        expect($ticket->followers()->pluck('id')->all())->toBe([$this->agentA->id]);
        $ticket->followers()->detach();
        expect($ticket->followers()->get())->toHaveCount(0);
    });
    $this->context->run('merchant-b', fn () => expect($this->ticketB->followers()->get())->toHaveCount(1));
});

it('denies loaded pivots and cached relationships after the tenant changes', function () {
    [$pivot, $relation] = $this->context->run('merchant-a', function () {
        $tag = Tag::create(['name' => 'A', 'slug' => 'a']);
        $this->ticketA->tags()->attach($tag);
        $this->ticketA->followers()->attach($this->agentA);
        $relation = $this->ticketA->followers();
        $relation->get();

        return [$this->ticketA->tags()->sole()->pivot, $relation];
    });
    $this->context->run('merchant-b', function () use ($pivot, $relation) {
        expect(fn () => $pivot->delete())->toThrow(AuthorizationException::class);
        expect(fn () => $relation->get())->toThrow(AuthorizationException::class);
    });
});

it('does not accept a foreign tenant pivot even when its related model is visible', function () {
    $this->context->run('merchant-a', function () {
        $tag = Tag::create(['name' => 'A', 'slug' => 'a']);
        Escalated::db()->table(Escalated::table('ticket_tag'))->insert([
            'tenant_id' => 'merchant-b', 'ticket_id' => $this->ticketA->id, 'tag_id' => $tag->id,
        ]);
        expect($this->ticketA->tags()->get())->toHaveCount(0);
        expect(Ticket::whereHas('tags')->count())->toBe(0);
    });
});

it('scopes bulk permanent deletion even when optional scopes are removed', function () {
    $this->context->run('merchant-a', function () {
        expect(Ticket::whereKey($this->ticketB->id)->forceDelete())->toBe(0);
        expect(Ticket::withoutGlobalScopes()->forceDelete())->toBe(1);
    });
    $this->context->run('merchant-b', fn () => expect(Ticket::sole()->id)->toBe($this->ticketB->id));
});

it('rejects relationship host-user saves before mutating the host record', function () {
    $this->context->run('merchant-a', function () {
        $this->agentB->name = 'Must not be saved';
        expect(fn () => $this->ticketA->followers()->saveQuietly($this->agentB))->toThrow(AuthorizationException::class);
        expect($this->agentB->fresh()->name)->not->toBe('Must not be saved');
    });
});

it('keeps raw report reads joins existence and writes inside the tenant', function () {
    $this->context->run('merchant-a', function () {
        $table = Escalated::table('tickets');
        $departments = Escalated::table('departments');
        $query = Escalated::query($table)->leftJoin($departments, $departments.'.id', '=', $table.'.department_id');
        expect($query->select($table.'.id')->pluck('id')->all())->toBe([$this->ticketA->id]);
        expect(Escalated::query($table)->where('id', $this->ticketB->id)->exists())->toBeFalse();
        expect(Escalated::query($table)->where('id', $this->ticketB->id)->orWhere('id', $this->ticketA->id)->pluck('id')->all())->toBe([$this->ticketA->id]);
        expect(Escalated::query($table)->update(['subject' => 'Raw A']))->toBe(1);
        expect(Escalated::query($table)->where('id', $this->ticketB->id)->delete())->toBe(0);
        Escalated::query(Escalated::table('contacts'))->insert(['email' => 'raw@example.test']);
        expect(Contact::sole()->tenant_id)->toBe('merchant-a');
    });
    $this->context->run('merchant-b', fn () => expect(Ticket::sole()->subject)->not->toBe('Raw A'));
});

it('generates real ticket references with model callbacks enabled', function () {
    $this->context->run('merchant-a', function () {
        $ticket = Ticket::create(['subject' => 'Generated', 'description' => 'Reference', 'priority' => 'medium', 'channel' => 'web']);
        expect($ticket->reference)->toStartWith('ESC-')
            ->and($ticket->fresh()->reference)->toBe($ticket->reference)
            ->and($ticket->tenant_id)->toBe('merchant-a');
    });
});

it('rejects qualified identity assignments and timestamp helpers on ownership columns', function () {
    $this->context->run('merchant-a', function () {
        expect(fn () => Ticket::query()->touch('tenant_id'))->toThrow(AuthorizationException::class);
        expect(fn () => Ticket::query()->update([Escalated::table('tickets').'.tenant_id' => 'merchant-b']))->toThrow(AuthorizationException::class);
        expect(fn () => $this->ticketA->followers()->rawUpdate(['name' => 'Changed']))->toThrow(AuthorizationException::class);
    });
});

it('scopes existence and uniqueness validation while preserving ordinary host validation', function () {
    $foreign = $this->context->run('merchant-b', fn () => Department::create(['name' => 'B', 'slug' => 'shared']));
    $this->context->run('merchant-a', function () use ($foreign) {
        expect(Validator::make(['department' => $foreign->id], ['department' => Rule::exists(Department::class, 'id')])->fails())->toBeTrue();
        expect(Validator::make(['slug' => 'shared'], ['slug' => Rule::unique(Department::class, 'slug')])->passes())->toBeTrue();
        expect(Validator::make(['agent' => $this->agentB->id], ['agent' => Rule::exists(get_class($this->agentB), 'id')])->fails())->toBeTrue();
        expect(Validator::make(['email' => $this->agentB->email], ['email' => Rule::unique(get_class($this->agentB), 'email')])->fails())->toBeTrue();
        Department::create(['name' => 'A', 'slug' => 'shared']);
        expect(Validator::make(['slug' => 'shared'], ['slug' => Rule::unique(Department::class, 'slug')])->fails())->toBeTrue();
    });
    expect(Validator::make(['agent' => $this->agentB->id], ['agent' => Rule::exists(get_class($this->agentB), 'id')])->passes())->toBeTrue();
});

it('rechecks lazy and eager host subject visibility after host membership changes', function () {
    $this->context->run('merchant-a', function () {
        $link = TicketSubjectLink::create([
            'ticket_id' => $this->ticketA->id, 'subject_type' => $this->agentA->getMorphClass(),
            'subject_id' => (string) $this->agentA->id, 'role' => 'requester',
        ]);
        expect($link->fresh()->subject->id)->toBe($this->agentA->id);
        app(TenantResolver::class)->members['merchant-a'] = [];
        expect($link->fresh()->subject)->toBeNull()
            ->and(TicketSubjectLink::with('subject')->sole()->subject)->toBeNull()
            ->and($this->ticketA->fresh()->assignee)->toBeNull();
    });
});

it('keeps pivot tenant predicates outside caller OR clauses for shared host users', function () {
    app(TenantResolver::class)->members['merchant-b'][] = $this->agentA->id;
    $this->context->run('merchant-a', fn () => $this->ticketA->followers()->attach($this->agentA));
    $this->context->run('merchant-b', fn () => $this->ticketB->followers()->attach($this->agentA));
    $this->context->run('merchant-a', function () {
        $followers = $this->ticketA->followers()->orWhere($this->agentA->qualifyColumn('id'), $this->agentA->id)->get();
        expect($followers)->toHaveCount(1)
            ->and($followers->sole()->pivot->ticket_id)->toBe($this->ticketA->id);
    });
});

it('validates toggle inputs before detaching and rejects forged loaded parent ownership', function () {
    $this->context->run('merchant-a', function () {
        $this->ticketA->followers()->attach($this->agentA);
        expect(fn () => $this->ticketA->followers()->toggle([$this->agentA->id, $this->agentB->id]))->toThrow(AuthorizationException::class);
        expect($this->ticketA->followers()->get())->toHaveCount(1);
        $this->ticketB->tenant_id = 'merchant-a';
        expect(fn () => $this->ticketB->followers()->attach($this->agentA))->toThrow(AuthorizationException::class);
    });
});
