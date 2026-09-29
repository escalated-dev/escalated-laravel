<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketActivity;
use Escalated\Laravel\Models\TicketSubjectLink;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\ApiOrder;
use Escalated\Laravel\Tests\Fixtures\ApiShipment;
use Escalated\Laravel\Tests\Fixtures\ApiStringUser;
use Escalated\Laravel\Tests\Fixtures\ApiTenantResolver;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
    Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
    config(['database.connections.subject_host' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'escalated.ticket_subjects.types' => ['shipment' => ApiShipment::class, 'order' => ApiOrder::class]]);
    foreach (['testing' => 'api_shipments', 'subject_host' => 'api_orders'] as $connection => $table) {
        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('account')->default('a');
        });
    }
    $this->agent = $this->createAgent(['email' => 'agent@example.test']);
    $this->requester = $this->createTestUser(['email' => 'requester@example.test']);
    $this->shipment = ApiShipment::create(['id' => '00017', 'name' => 'Shipment A']);
    $this->order = ApiOrder::create(['id' => 'order_01', 'name' => 'Order A']);
    $this->token = ApiToken::createToken($this->agent, 'Agent', ['agent'])['plainTextToken'];
    $this->withToken($this->token);
    $this->payload = [
        'subject' => 'Parcel damaged', 'description' => 'Please investigate.',
        'requester' => ['id' => $this->requester->id], 'metadata' => ['source' => 'merchant'],
        'external_reference' => 'TRACK-1',
        'subjects' => [
            ['type' => 'shipment', 'id' => '00017', 'role' => 'parcel'],
            ['type' => 'order', 'id' => 'order_01', 'role' => 'purchase'],
        ],
    ];
});

it('creates and reads requester metadata and subjects across three real connections', function () {
    expect(Escalated::schema()->hasTable('users'))->toBeFalse()
        ->and(Escalated::schema()->hasTable('api_shipments'))->toBeFalse()
        ->and(Escalated::schema()->hasTable('api_orders'))->toBeFalse();
    $response = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()
        ->assertJsonPath('data.requester.kind', 'user')->assertJsonPath('data.requester.id', $this->requester->id)
        ->assertJsonPath('data.metadata.source', 'merchant')->assertJsonPath('data.external_reference', 'TRACK-1')
        ->assertJsonPath('data.subjects.0.id', '00017')->assertJsonPath('data.subjects.0.title', 'Shipment A')
        ->assertJsonPath('data.subjects.1.title', 'Order A')->json('data');
    $this->getJson('/support/api/v1/tickets/'.$response['reference'])->assertOk()->assertJsonCount(2, 'data.subjects');
    $this->getJson('/support/api/v1/tickets/'.$response['reference'].'/subjects')->assertOk()->assertJsonCount(2, 'data');
    expect(TicketActivity::where('type', 'status_changed')->first()->causer_id)->toBe($this->agent->id);
});

it('resolves named host requesters with string primary keys without coercion', function () {
    Schema::create('api_string_users', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->boolean('is_agent')->default(false);
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });
    $agent = new ApiStringUser;
    $agent->forceFill(['id' => 'usr_agent_01', 'name' => 'Agent', 'email' => 'str-agent@example.test', 'password' => 'test', 'is_agent' => true])->save();
    $requester = new ApiStringUser;
    $requester->forceFill(['id' => 'usr_recipient_01', 'name' => 'Recipient', 'email' => 'str-recipient@example.test', 'password' => 'test'])->save();
    config(['escalated.user_model' => ApiStringUser::class]);
    $token = ApiToken::createToken($agent, 'String agent', ['agent'])['plainTextToken'];
    $this->payload['requester'] = ['id' => $requester->id];
    $this->withToken($token)->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()
        ->assertJsonPath('data.requester.id', 'usr_recipient_01')
        ->assertJsonPath('data.requester.name', 'Recipient');
});

it('creates a named contact without a host user or public guest credential', function () {
    $this->payload['requester'] = ['name' => 'Recipient', 'email' => 'RECIPIENT@example.test'];
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()
        ->assertJsonPath('data.requester.kind', 'contact')->assertJsonPath('data.requester.name', 'Recipient')
        ->assertJsonPath('data.requester.email', 'recipient@example.test')->json('data');
    $this->payload['requester']['name'] = 'Do not replace';
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->assertJsonPath('data.requester.name', 'Recipient');
    expect(Contact::count())->toBe(1)->and(TestUser::count())->toBe(2);
    $ticket = Ticket::where('reference', $created['reference'])->first();
    expect($ticket->guest_token)->toBeNull()->and($ticket->guest_access_hash)->toBeNull()
        ->and($ticket->guest_email_verified_at)->toBeNull()->and($ticket->requester_id)->toBeNull();
});

it('replaces the complete subject set with stable link ids and no partial invalid updates', function () {
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    $path = '/support/api/v1/tickets/'.$created['reference'].'/subjects';
    $entries = array_reverse($this->payload['subjects']);
    $entries[0]['role'] = 'primary';
    $this->putJson($path, ['subjects' => $entries])->assertOk()->assertJsonPath('data.0.link_id', $created['subjects'][1]['link_id'])
        ->assertJsonPath('data.0.position', 0)->assertJsonPath('data.0.role', 'primary');
    $this->putJson($path, ['subjects' => $entries])->assertOk()->assertJsonCount(2, 'data');
    $entries[] = ['type' => 'shipment', 'id' => 'missing'];
    $this->putJson($path, ['subjects' => $entries])->assertUnprocessable();
    $this->getJson($path)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.role', 'primary');
    $this->putJson($path, ['subjects' => []])->assertOk()->assertJsonCount(0, 'data');
});

it('rejects duplicate aliases for the same subject before creating anything', function () {
    $this->payload['subjects'][] = ['type' => ApiShipment::class, 'id' => '00017'];
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('subjects.2.id');
    expect(Ticket::count())->toBe(0);
});

it('rejects malformed integration fields before any write', function (array $override) {
    $this->postJson('/support/api/v1/tickets', array_replace($this->payload, $override))->assertUnprocessable();
    expect(Ticket::count())->toBe(0)->and(Contact::count())->toBe(0)->and(TicketSubjectLink::count())->toBe(0);
})->with([
    'null requester' => [['requester' => null]],
    'empty requester' => [['requester' => []]],
    'mixed requester' => [['requester' => ['id' => 1, 'email' => 'x@example.test']]],
    'null key' => [['requester' => ['id' => null]]],
    'boolean key' => [['requester' => ['id' => true]]],
    'huge integer key' => [['requester' => ['id' => str_repeat('9', 100)]]],
    'array name' => [['requester' => ['name' => ['bad'], 'email' => 'x@example.test']]],
    'invalid email' => [['requester' => ['name' => 'X', 'email' => 'bad']]],
    'oversize metadata' => [['metadata' => ['large' => str_repeat('a', 16385)]]],
    'arbitrary class' => [['subjects' => [['type' => TestUser::class, 'id' => 1]]]],
    'boolean subject key' => [['subjects' => [['type' => 'shipment', 'id' => false]]]],
    'subject tenant input' => [['subjects' => [['type' => 'shipment', 'id' => '00017', 'tenant_id' => 'foreign']]]],
    'subject role too long' => [['subjects' => [['type' => 'shipment', 'id' => '00017', 'role' => str_repeat('a', 256)]]]],
    'subject object instead of list' => [['subjects' => ['bad' => ['type' => 'shipment', 'id' => '00017']]]],
]);

it('rolls back contact ticket links and activity when the final subject write fails', function () {
    Event::fake([TicketCreated::class]);
    TicketSubjectLink::creating(function ($link) {
        if ($link->subject_id === 'order_01') {
            throw new RuntimeException('Link write failed');
        }
    });
    $this->payload['requester'] = ['name' => 'Recipient', 'email' => 'recipient@example.test'];
    $this->withoutExceptionHandling();
    expect(fn () => $this->postJson('/support/api/v1/tickets', $this->payload))->toThrow(RuntimeException::class, 'Link write failed');
    expect(Ticket::count())->toBe(0)->and(Contact::count())->toBe(0)->and(TicketSubjectLink::count())->toBe(0)->and(TicketActivity::count())->toBe(0);
    Event::assertNotDispatched(TicketCreated::class);
});

it('waits for package commit with a host transaction open and suppresses a later rollback', function () {
    $observed = [];
    Event::listen(TicketCreated::class, function ($event) use (&$observed) {
        $observed[] = [$event->ticket->reference, $event->ticket->subjects()->count(), $event->ticket->metadata['source']];
    });
    DB::connection('testing')->beginTransaction();
    Escalated::db()->beginTransaction();
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated();
    expect($observed)->toBe([]);
    Escalated::db()->commit();
    expect($observed)->toHaveCount(1)->and($observed[0][1])->toBe(2)->and($observed[0][2])->toBe('merchant');
    Escalated::db()->beginTransaction();
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated();
    Escalated::db()->rollBack();
    expect($observed)->toHaveCount(1)->and(Ticket::count())->toBe(1);
    DB::connection('testing')->rollBack();
});

it('honors host object authorization on write and on already loaded presentation', function () {
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    config(['escalated.ticket_subjects.authorize' => fn () => false]);
    $this->getJson('/support/api/v1/tickets/'.$created['reference'])->assertOk()
        ->assertJsonPath('data.subjects.0.missing', true)->assertJsonMissing(['title' => 'Shipment A']);
    $this->putJson('/support/api/v1/tickets/'.$created['reference'].'/subjects', ['subjects' => $this->payload['subjects']])->assertUnprocessable();
    expect(TicketSubjectLink::count())->toBe(2);
});

it('rejects advanced operations on remote modes before any outbound request or local write', function (string $mode) {
    Http::fake();
    config(['escalated.mode' => $mode]);
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('unsupported_driver');
    expect(Ticket::count())->toBe(0)->and(Contact::count())->toBe(0);
    Http::assertNothingSent();
})->with(['cloud', 'synced']);

it('requires agent ability and policy approval for subject operations', function () {
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    $path = '/support/api/v1/tickets/'.$created['reference'].'/subjects';
    $token = ApiToken::createToken($this->requester, 'Customer', ['customer'])['plainTextToken'];
    $this->withToken($token)->getJson($path)->assertForbidden();
    $this->putJson($path, ['subjects' => []])->assertForbidden();
    Gate::before(fn ($user, $ability) => $ability === 'update' ? false : null);
    $this->withToken($this->token)->putJson($path, ['subjects' => []])->assertForbidden();
    expect(TicketSubjectLink::count())->toBe(2);
});

function enableAgentApiTenancy($test): void
{
    $test->resolver = new ApiTenantResolver;
    $test->resolver->members = ['a' => [$test->agent->id, $test->requester->id]];
    app()->instance(TenantResolver::class, $test->resolver);
    config(['escalated.tenancy.enabled' => true]);
    $test->context = app(TenantContext::class);
    $test->token = $test->context->run('a', fn () => ApiToken::createToken($test->agent, 'A', ['agent'])['plainTextToken']);
    $test->withToken($test->token);
}

it('rejects foreign host records and independently checks visibility after discovery', function () {
    enableAgentApiTenancy($this);
    ApiShipment::create(['id' => 'foreign', 'name' => 'Foreign shipment', 'account' => 'b']);
    $foreign = $this->payload;
    $foreign['subjects'][0]['id'] = 'foreign';
    $this->postJson('/support/api/v1/tickets', $foreign)->assertUnprocessable()->assertJsonValidationErrors('subjects.0.id');
    $this->resolver->denied = [ApiShipment::class.':00017'];
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('subjects.0.id');
    $this->resolver->denied = [TestUser::class.':'.$this->requester->id];
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('requester.id');
    $this->context->run('a', fn () => expect(Ticket::count())->toBe(0));
});

it('keeps equal contact emails and tracking references separate between merchants', function () {
    enableAgentApiTenancy($this);
    $this->payload['requester'] = ['name' => 'Recipient', 'email' => 'same@example.test'];
    $this->payload['subjects'] = [];
    $a = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    $other = $this->createAgent(['email' => 'other-agent@example.test']);
    $this->resolver->members['b'] = [$other->id];
    $token = $this->context->run('b', fn () => ApiToken::createToken($other, 'B', ['agent'])['plainTextToken']);
    $this->withToken($token);
    $this->getJson('/support/api/v1/tickets/'.$a['reference'].'/subjects')->assertNotFound();
    $b = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    expect($a['requester']['id'])->not->toBe($b['requester']['id']);
    foreach (['a', 'b'] as $tenant) {
        $this->context->run($tenant, fn () => expect(Contact::count())->toBe(1)->and(Ticket::count())->toBe(1));
    }
});

it('restores captured tenant context when an outer transaction commits after the request', function () {
    enableAgentApiTenancy($this);
    $observed = [];
    Event::listen(TicketCreated::class, function ($event) use (&$observed) {
        $observed[] = [app(TenantContext::class)->current(), $event->ticket->subjects()->count()];
    });
    Escalated::db()->beginTransaction();
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated();
    expect($this->context->current())->toBeNull()->and($observed)->toBe([]);
    Escalated::db()->commit();
    expect($observed)->toBe([['a', 2]])->and($this->context->current())->toBeNull();
});

it('rechecks a subject membership change at the final link write', function () {
    enableAgentApiTenancy($this);
    config(['escalated.ticket_subjects.authorize' => function ($actor, $subject) {
        $this->resolver->denied[] = $subject::class.':'.$subject->getKey();

        return true;
    }]);
    $this->postJson('/support/api/v1/tickets', $this->payload)->assertForbidden();
    $this->context->run('a', fn () => expect(Ticket::count())->toBe(0)->and(TicketSubjectLink::count())->toBe(0));
});

it('does not update retained links after their subject loses merchant membership', function () {
    enableAgentApiTenancy($this);
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    config(['escalated.ticket_subjects.authorize' => function ($actor, $subject) {
        $this->resolver->denied[] = $subject::class.':'.$subject->getKey();

        return true;
    }]);
    $entries = $this->payload['subjects'];
    $entries[0]['role'] = 'must-not-save';
    $this->putJson('/support/api/v1/tickets/'.$created['reference'].'/subjects', ['subjects' => $entries])->assertForbidden();
    $this->context->run('a', fn () => expect(TicketSubjectLink::where('subject_id', '00017')->first()->role)->toBe('parcel'));
});
