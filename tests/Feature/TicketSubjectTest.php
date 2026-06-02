<?php

use Escalated\Laravel\Concerns\PresentsAsTicketSubject;
use Escalated\Laravel\Contracts\TicketSubject;
use Escalated\Laravel\Http\Resources\TicketResource;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketSubjectLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A host-app model that opts into being a ticket subject. Uses a STRING primary
// key to also prove subject_id is host-key-type agnostic (int/uuid/string).
class FakeProject extends Model implements TicketSubject
{
    use PresentsAsTicketSubject;

    protected $table = 'fake_projects';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function ticketSubjectSubtitle(): ?string
    {
        return 'Project · '.$this->account;
    }

    public function ticketSubjectUrl(): ?string
    {
        return 'https://app.test/projects/'.$this->getKey();
    }

    public function ticketSubjectColor(): ?string
    {
        return '#2563eb';
    }

    public function ticketSubjectIcon(): ?string
    {
        return 'folder';
    }
}

beforeEach(function () {
    Schema::create('fake_projects', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('account')->nullable();
    });

    config()->set('escalated.ticket_subjects.types', [FakeProject::class]);
});

afterEach(fn () => Schema::dropIfExists('fake_projects'));

it('attaches a host model as a ticket subject, preserving a string key', function () {
    $ticket = Ticket::factory()->create();
    $project = FakeProject::create(['id' => 'prj_9f1c', 'name' => 'Acme Redesign', 'account' => 'Acme']);

    $link = $ticket->attachSubject($project, 'project');

    expect($link)->toBeInstanceOf(TicketSubjectLink::class)
        ->and($ticket->subjects()->count())->toBe(1)
        ->and($link->subject_type)->toBe(FakeProject::class)
        ->and($link->subject_id)->toBe('prj_9f1c')
        ->and($link->role)->toBe('project')
        ->and($link->subject->is($project))->toBeTrue();
});

it('is idempotent on the ticket+type+id key and updates the role', function () {
    $ticket = Ticket::factory()->create();
    $project = FakeProject::create(['id' => 'p1', 'name' => 'A']);

    $ticket->attachSubject($project);
    $ticket->attachSubject($project, 'account');

    expect($ticket->subjects()->count())->toBe(1)
        ->and($ticket->subjects()->first()->role)->toBe('account');
});

it('serializes subjects through the presentation contract', function () {
    $ticket = Ticket::factory()->create();
    $project = FakeProject::create(['id' => '7', 'name' => 'Acme Redesign', 'account' => 'Acme']);
    $ticket->attachSubject($project, 'project');

    $data = (new TicketResource($ticket->load('subjects.subject')))->toArray(request());

    expect($data['subjects'])->toHaveCount(1);
    expect($data['subjects'][0])->toMatchArray([
        'type' => FakeProject::class,
        'id' => '7',
        'role' => 'project',
        'title' => 'Acme Redesign',
        'subtitle' => 'Project · Acme',
        'url' => 'https://app.test/projects/7',
        'color' => '#2563eb',
        'icon' => 'folder',
        'missing' => false,
    ]);
});

it('detaches a subject', function () {
    $ticket = Ticket::factory()->create();
    $project = FakeProject::create(['id' => '1', 'name' => 'A']);
    $ticket->attachSubject($project);

    expect($ticket->detachSubject($project))->toBe(1)
        ->and($ticket->subjects()->count())->toBe(0);
});

it('syncs subjects, replacing existing and preserving order', function () {
    $ticket = Ticket::factory()->create();
    $a = FakeProject::create(['id' => 'a', 'name' => 'A']);
    $b = FakeProject::create(['id' => 'b', 'name' => 'B']);
    $c = FakeProject::create(['id' => 'c', 'name' => 'C']);

    $ticket->attachSubject($a);
    $ticket->syncSubjects([[$b, 'primary'], $c]);

    $links = $ticket->subjects()->get();
    expect($links)->toHaveCount(2)
        ->and($links[0]->subject_id)->toBe('b')
        ->and($links[0]->role)->toBe('primary')
        ->and($links[0]->position)->toBe(0)
        ->and($links[1]->subject_id)->toBe('c')
        ->and($links[1]->position)->toBe(1);
});

it('rejects attaching a type outside the configured allowlist', function () {
    config()->set('escalated.ticket_subjects.types', ['App\\Models\\SomethingElse']);

    $ticket = Ticket::factory()->create();
    $project = FakeProject::create(['id' => '1', 'name' => 'A']);

    expect(fn () => $ticket->attachSubject($project))->toThrow(InvalidArgumentException::class);
});

it('allows any model programmatically when no allowlist is configured', function () {
    config()->set('escalated.ticket_subjects.types', []);

    $ticket = Ticket::factory()->create();
    $project = FakeProject::create(['id' => '1', 'name' => 'A']);

    expect($ticket->attachSubject($project))->toBeInstanceOf(TicketSubjectLink::class);
});
