<?php

use Escalated\Laravel\Contracts\TicketAttachable;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('ticket_context_projects', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('code')->nullable();
        $table->timestamps();
    });
});

it('generates a reference from the ticket id', function () {
    $ticket = Ticket::factory()->create();
    $ref = $ticket->generateReference();
    expect($ref)->toStartWith('ESC-');
    expect($ref)->toBe(sprintf('ESC-%05d', $ticket->id));
});

it('uses dynamic table name from config', function () {
    $ticket = new Ticket;
    expect($ticket->getTable())->toBe('escalated_tickets');
});

it('casts status to enum', function () {
    $ticket = Ticket::factory()->create();
    expect($ticket->status)->toBeInstanceOf(TicketStatus::class);
});

it('casts priority to enum', function () {
    $ticket = Ticket::factory()->create();
    expect($ticket->priority)->toBeInstanceOf(TicketPriority::class);
});

it('scopes open tickets correctly', function () {
    Ticket::factory()->create(['status' => TicketStatus::Open]);
    Ticket::factory()->create(['status' => TicketStatus::InProgress]);
    Ticket::factory()->create(['status' => TicketStatus::Resolved]);
    Ticket::factory()->create(['status' => TicketStatus::Closed]);

    expect(Ticket::open()->count())->toBe(2);
});

it('scopes unassigned tickets', function () {
    Ticket::factory()->create(['assigned_to' => null]);
    Ticket::factory()->create(['assigned_to' => 1]);

    expect(Ticket::unassigned()->count())->toBe(1);
});

it('scopes tickets by assignee', function () {
    Ticket::factory()->create(['assigned_to' => 1]);
    Ticket::factory()->create(['assigned_to' => 2]);
    Ticket::factory()->create(['assigned_to' => 1]);

    expect(Ticket::assignedTo(1)->count())->toBe(2);
});

it('scopes breached SLA tickets', function () {
    Ticket::factory()->create(['sla_first_response_breached' => true]);
    Ticket::factory()->create(['sla_resolution_breached' => true]);
    Ticket::factory()->create(['sla_first_response_breached' => false, 'sla_resolution_breached' => false]);

    expect(Ticket::breachedSla()->count())->toBe(2);
});

it('scopes search by subject, reference, and description', function () {
    Ticket::factory()->create(['subject' => 'Login issue', 'reference' => 'ESC-00001']);
    Ticket::factory()->create(['subject' => 'Payment bug', 'reference' => 'ESC-00002']);

    expect(Ticket::search('Login')->count())->toBe(1);
    expect(Ticket::search('ESC-00002')->count())->toBe(1);
});

it('determines if ticket is open', function () {
    $open = Ticket::factory()->create(['status' => TicketStatus::Open]);
    $resolved = Ticket::factory()->create(['status' => TicketStatus::Resolved]);

    expect($open->isOpen())->toBeTrue();
    expect($resolved->isOpen())->toBeFalse();
});

it('attaches contextual models to tickets', function () {
    $ticket = Ticket::factory()->create();
    $project = TicketContextProject::create([
        'name' => 'Website rebuild',
        'code' => 'WEB-24',
    ]);

    $context = $ticket->attachContext($project, [
        'label' => 'Affected project',
        'metadata' => ['source' => 'test'],
    ]);
    $duplicate = $ticket->attachContext($project);

    expect($context)->toBeInstanceOf(TicketContext::class);
    expect($duplicate->id)->toBe($context->id);
    expect($ticket->contexts()->count())->toBe(1);
    expect($ticket->contexts()->first()->attachable)->toBeInstanceOf(TicketContextProject::class);

    expect($ticket->detachContext($project))->toBe(1);
    expect($ticket->contexts()->count())->toBe(0);
});

it('exposes ticket context display attributes from the attachable contract', function () {
    $ticket = Ticket::factory()->create();
    $project = TicketContextProject::create([
        'name' => 'Billing migration',
        'code' => 'BILL',
    ]);

    $context = $ticket->attachContext($project)->load('attachable');

    expect($context->display)->toBe([
        'url' => 'https://example.test/projects/'.$project->id,
        'title' => 'Billing migration',
        'subtitle' => 'Project BILL',
        'color' => '#2563eb',
        'icon' => 'folder-kanban',
        'badge' => 'Project',
        'metadata' => ['code' => 'BILL'],
    ]);
});

class TicketContextProject extends Model implements TicketAttachable
{
    protected $table = 'ticket_context_projects';

    protected $guarded = ['id'];

    public function ticketAttachableUrl(): ?string
    {
        return 'https://example.test/projects/'.$this->id;
    }

    public function ticketAttachableTitle(): string
    {
        return $this->name;
    }

    public function ticketAttachableSubtitle(): ?string
    {
        return 'Project '.$this->code;
    }

    public function ticketAttachableColor(): string
    {
        return '#2563eb';
    }

    public function ticketAttachableIcon(): string
    {
        return 'folder-kanban';
    }

    public function ticketAttachableBadge(): ?string
    {
        return 'Project';
    }

    public function ticketAttachableMetadata(): array
    {
        return ['code' => $this->code];
    }
}
