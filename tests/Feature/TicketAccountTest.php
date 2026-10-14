<?php

use Escalated\Laravel\Contracts\TicketAccountResolver;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Automation;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Policies\TicketPolicy;
use Escalated\Laravel\Services\AgentTicketCreator;
use Escalated\Laravel\Services\AutomationRunner;
use Escalated\Laravel\Services\EscalationService;
use Escalated\Laravel\Services\ReportingService;
use Escalated\Laravel\Support\TicketAccounts;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $this->account = $this->createTestUser(['email' => 'account@example.test']);
    $this->otherAccount = $this->createTestUser(['email' => 'other-account@example.test']);
    $this->agent = $this->createAgent();
    $this->resolver = new class implements TicketAccountResolver
    {
        public array $accounts = [];

        public function resolve(Model $model): ?Model
        {
            return $this->accounts[$model->getMorphClass().':'.$model->getKey()] ?? null;
        }
    };
    $this->resolver->accounts[TestUser::class.':'.$this->agent->id] = $this->account;
    app()->instance(TicketAccountResolver::class, $this->resolver);
    config(['escalated.accounts.enabled' => true]);
});

it('derives the account from a requester before ticket creation listeners run', function () {
    $ticket = Ticket::factory()->create(['requester_type' => TestUser::class, 'requester_id' => $this->agent->id]);
    expect($ticket->account_type)->toBe($this->account->getMorphClass())
        ->and((string) $ticket->account_id)->toBe((string) $this->account->id)
        ->and($ticket->fresh()->account->is($this->account))->toBeTrue();
});

it('derives an unallocated account from a subject without replacing an existing account', function () {
    $ticket = Ticket::factory()->create();
    $this->resolver->accounts[TestUser::class.':'.$this->otherAccount->id] = $this->otherAccount;
    $ticket->attachSubject($this->agent);
    expect((string) $ticket->fresh()->account_id)->toBe((string) $this->account->id);
    $ticket->attachSubject($this->otherAccount);
    expect((string) $ticket->fresh()->account_id)->toBe((string) $this->account->id);
});

it('keeps account resolution off by default', function () {
    config(['escalated.accounts.enabled' => false]);
    $ticket = Ticket::factory()->create(['requester_type' => TestUser::class, 'requester_id' => $this->agent->id]);
    $ticket->attachSubject($this->agent);
    expect($ticket->fresh()->account_id)->toBeNull();
});

it('intersects account and department visibility and compares both morph columns', function () {
    $department = Department::factory()->create();
    $department->agents()->attach($this->agent);
    $owned = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->account->id, 'department_id' => $department->id]);
    $foreign = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->otherAccount->id, 'department_id' => $department->id]);
    Ticket::factory()->create(['account_type' => 'another-account-type', 'account_id' => $this->account->id, 'department_id' => $department->id]);
    Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->account->id]);
    Ticket::factory()->create(['department_id' => $department->id]);
    config(['escalated.visibility' => 'department', 'escalated.accounts.scope_agents' => true]);
    $this->actingAs($this->agent);
    expect(Ticket::pluck('id')->all())->toBe([$owned->id])
        ->and((new TicketPolicy)->update($this->agent, $foreign))->toBeFalse();
    $this->resolver->accounts = [];
    expect(Ticket::count())->toBe(0);
    $this->actingAs($this->createAdmin());
    expect(Ticket::count())->toBe(5);
});

it('supports account visibility independently of departments', function () {
    $ticket = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->account->id]);
    Ticket::factory()->create();
    $this->actingAs($this->agent);
    expect(Ticket::count())->toBe(2);
    config(['escalated.accounts.scope_agents' => true]);
    expect(Ticket::pluck('id')->all())->toBe([$ticket->id]);
});

it('filters API tickets by the complete account identity without widening visibility', function () {
    $owned = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->account->id]);
    $foreign = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->otherAccount->id]);
    $headers = ['Authorization' => 'Bearer '.ApiToken::createToken($this->agent, 'Account', ['agent'])['plainTextToken']];
    $this->getJson(route('escalated.api.tickets.index', ['account_type' => TestUser::class, 'account_id' => (string) $this->account->id]), $headers)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.account.id', (string) $this->account->id);
    $this->getJson(route('escalated.api.tickets.index', ['account_id' => (string) $this->account->id]), $headers)->assertUnprocessable();
    config(['escalated.accounts.scope_agents' => true]);
    $this->getJson(route('escalated.api.tickets.index', ['account_type' => TestUser::class, 'account_id' => (string) $this->otherAccount->id]), $headers)
        ->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson(route('escalated.api.tickets.show', $foreign), $headers)->assertNotFound();
    $this->postJson(route('escalated.api.tickets.reply', $foreign), ['body' => 'Hidden'], $headers)->assertNotFound();
    $this->getJson(route('escalated.api.tickets.show', $owned), $headers)->assertOk();
});

it('resolves a subject account before aggregate creation dispatches its event', function () {
    $this->resolver->accounts[TestUser::class.':'.$this->agent->id] = null;
    $this->resolver->accounts[TestUser::class.':'.$this->account->id] = $this->account;
    config(['escalated.ticket_subjects.types' => ['account' => TestUser::class]]);
    $seen = null;
    Event::listen(TicketCreated::class, function ($event) use (&$seen) {
        $seen = $event->ticket->account_id;
    });
    $ticket = app(AgentTicketCreator::class)->create($this->agent, [
        'subject' => 'Subject account', 'description' => 'Account from subject', 'ticket_type' => 'incident',
        'subjects' => [['type' => 'account', 'id' => $this->account->id]],
    ]);
    expect((string) $ticket->account_id)->toBe((string) $this->account->id);
    expect((string) $seen)->toBe((string) $this->account->id)
        ->and($ticket->ticket_type)->toBe('incident');
});

it('stores UUID account identities and respects morph aliases', function () {
    Schema::create('ticket_test_accounts', function ($table) {
        $table->uuid('id')->primary();
    });
    $account = new class extends Model
    {
        protected $table = 'ticket_test_accounts';

        protected $keyType = 'string';

        public $incrementing = false;

        public $timestamps = false;

        protected $guarded = [];
    };
    Relation::morphMap(['test-account' => $account::class]);
    try {
        $account->id = '6e873d68-60b3-4e95-91a0-044a100831be';
        $account->save();
        $this->resolver->accounts[TestUser::class.':'.$this->agent->id] = $account;
        $ticket = Ticket::factory()->create(['requester_type' => TestUser::class, 'requester_id' => $this->agent->id]);
        expect($ticket->account_type)->toBe('test-account')->and($ticket->fresh()->account->is($account))->toBeTrue();
        config(['escalated.accounts.scope_agents' => true]);
        $this->actingAs($this->agent);
        expect(Ticket::forAccount($account)->pluck('id')->all())->toBe([$ticket->id]);
    } finally {
        Relation::morphMap([], false);
    }
});

it('fails closed when account scoping is enabled without an account resolver', function () {
    Ticket::factory()->create();
    config(['escalated.accounts.enabled' => false, 'escalated.accounts.scope_agents' => true]);
    $this->actingAs($this->agent);
    expect(Ticket::count())->toBe(0);
});

it('enforces account visibility for bulk changes reports and authenticated widget access', function () {
    $owned = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->account->id, 'priority' => 'low']);
    $foreign = Ticket::factory()->create(['account_type' => TestUser::class, 'account_id' => $this->otherAccount->id, 'priority' => 'low']);
    $token = $this->guestToken($foreign);
    EscalatedSettings::set('widget_enabled', '1');
    config(['escalated.accounts.scope_agents' => true]);
    $this->actingAs($this->agent)->post(route('escalated.agent.tickets.bulk'), [
        'ticket_ids' => [$owned->id, $foreign->id], 'action' => 'priority', 'value' => 'high',
    ])->assertRedirect();
    expect($owned->fresh()->priority->value)->toBe('high')->and($foreign->fresh()->priority->value)->toBe('low');
    $reports = app(ReportingService::class);
    expect(array_sum(array_column($reports->ticketsByDepartment(7), 'volume')))->toBe(1);
    $this->getJson(route('escalated.widget.tickets.status', $foreign->reference), ['Authorization' => 'Bearer '.$token])->assertNotFound();
});

it('rejects a missing resolver when account association is enabled', function () {
    app()->forgetInstance(TicketAccountResolver::class);
    $this->expectException(LogicException::class);
    Ticket::factory()->create(['requester_type' => TestUser::class, 'requester_id' => $this->agent->id]);
});

it('does not overwrite an account when attaching through a stale ticket instance', function () {
    $ticket = Ticket::factory()->create();
    $stale = $ticket->fresh();
    $ticket->account()->associate($this->otherAccount);
    $ticket->save();
    $stale->attachSubject($this->agent);
    expect((string) $stale->fresh()->account_id)->toBe((string) $this->otherAccount->id);
});

it('rejects a partial account association', function () {
    $this->expectException(InvalidArgumentException::class);
    Ticket::factory()->create(['account_type' => TestUser::class]);
});

it('does not automatically assign tickets to agents who cannot access their account', function () {
    $department = Department::factory()->create();
    $department->agents()->attach($this->agent);
    config(['escalated.accounts.scope_agents' => true]);
    $ticket = Ticket::factory()->create(['department_id' => $department->id, 'account_type' => TestUser::class, 'account_id' => $this->otherAccount->id]);
    expect($ticket->fresh()->assigned_to)->toBeNull();
});

it('compares account identities exactly in scopes filters and routing', function (string $field, string $suffix) {
    $identity = ['account_type' => $this->account->getMorphClass(), 'account_id' => (string) $this->account->id];
    $owned = Ticket::factory()->create($identity);
    $different = $identity;
    $different[$field] .= $suffix;
    Ticket::factory()->create($different);
    expect(Ticket::forAccount($this->account)->pluck('id')->all())->toBe([$owned->id]);
    $condition = ['type' => $identity['account_type'], 'id' => $identity['account_id']];
    expect(TicketAccounts::constrain(Ticket::query(), $condition)->pluck('id')->all())->toBe([$owned->id]);

    foreach ([EscalationService::class, AutomationRunner::class] as $service) {
        $ruleClass = $service === EscalationService::class
            ? EscalationRule::class : Automation::class;
        foreach ([['field' => 'account', 'value' => $condition], ['field' => $field, 'value' => $identity[$field]]] as $rule) {
            $model = new $ruleClass(['conditions' => [$rule]]);
            $method = new ReflectionMethod($service, 'findMatchingTickets');
            $method->setAccessible(true);
            expect($method->invoke(app($service), $model)->pluck('id')->all())->toBe([$owned->id]);
        }
    }
    config(['escalated.accounts.scope_agents' => true]);
    $this->actingAs($this->agent);
    expect(Ticket::pluck('id')->all())->toBe([$owned->id]);
})->with(['account_type', 'account_id'])->with([' ', '  ']);
