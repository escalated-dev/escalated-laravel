<?php

use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketActivity;
use Escalated\Laravel\Models\TicketSubjectLink;
use Escalated\Laravel\Services\AgentTicketCreator;
use Escalated\Laravel\Tests\Fixtures\ApiShipment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('api_shipments', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
    });
    config(['escalated.ticket_subjects.types' => ['shipment' => ApiShipment::class]]);
    Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
    $this->actor = $this->createAgent();
    $this->withToken(ApiToken::createToken($this->actor, 'Agent', ['agent'])['plainTextToken']);
    $this->one = ApiShipment::create(['id' => 'a', 'name' => 'First']);
    $this->two = ApiShipment::create(['id' => 'b', 'name' => 'Second']);
    $this->payload = [
        'subject' => 'Delivery', 'description' => 'Details',
        'requester' => ['name' => 'Recipient', 'email' => 'recipient@example.test'],
        'subjects' => [['type' => 'shipment', 'id' => 'a', 'role' => 'parcel']],
    ];
});

afterEach(fn () => Schema::dropIfExists('api_shipments'));

it('persists the aggregate and replaces ordered links on the configured database driver', function () {
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    $path = '/support/api/v1/tickets/'.$created['reference'].'/subjects';
    $entries = [['type' => 'shipment', 'id' => 'b'], ['type' => 'shipment', 'id' => 'a', 'role' => 'retained']];
    $this->putJson($path, ['subjects' => $entries])->assertOk()->assertJsonPath('data.1.link_id', $created['subjects'][0]['link_id'])
        ->assertJsonPath('data.1.role', 'retained')->assertJsonPath('data.1.position', 1);
    $this->getJson($path)->assertOk()->assertJsonCount(2, 'data');
    $this->putJson($path, ['subjects' => []])->assertOk()->assertJsonCount(0, 'data');
    expect(Contact::count())->toBe(1);
});

it('retains the complete prior subject set when replacement fails after its first write', function () {
    $created = $this->postJson('/support/api/v1/tickets', $this->payload)->assertCreated()->json('data');
    TicketSubjectLink::creating(fn ($link) => $link->subject_id === 'b' ? throw new RuntimeException('Link failed') : null);
    $this->withoutExceptionHandling();
    expect(fn () => $this->putJson('/support/api/v1/tickets/'.$created['reference'].'/subjects', ['subjects' => [
        ['type' => 'shipment', 'id' => 'a', 'role' => 'changed'], ['type' => 'shipment', 'id' => 'b'],
    ]]))->toThrow(RuntimeException::class, 'Link failed');
    $link = TicketSubjectLink::sole();
    expect($link->role)->toBe('parcel')->and($link->id)->toBe($created['subjects'][0]['link_id']);
});

it('rolls back the entire aggregate if the final activity insert fails', function () {
    Event::fake([TicketCreated::class]);
    TicketActivity::creating(fn () => throw new RuntimeException('Activity failed'));
    expect(fn () => app(AgentTicketCreator::class)->create($this->actor, $this->payload))->toThrow(RuntimeException::class, 'Activity failed');
    expect(Ticket::count())->toBe(0)->and(Contact::count())->toBe(0)->and(TicketSubjectLink::count())->toBe(0);
    Event::assertNotDispatched(TicketCreated::class);
});

it('retains the legacy agent requester when no integration fields are supplied', function () {
    $created = $this->postJson('/support/api/v1/tickets', ['subject' => 'Ordinary', 'description' => 'Details'])->assertCreated()
        ->assertJsonPath('data.requester.kind', 'user')->assertJsonPath('data.requester.id', $this->actor->id);
    expect(Ticket::count())->toBe(1);
});

it('keeps programmatic replacement atomic when its last model is invalid', function () {
    $ticket = Ticket::factory()->create();
    $link = $ticket->attachSubject($this->one, 'original');
    expect(fn () => $ticket->syncSubjects([$this->two, ['invalid']]))->toThrow(InvalidArgumentException::class);
    expect($ticket->subjects()->sole()->id)->toBe($link->id);
});
