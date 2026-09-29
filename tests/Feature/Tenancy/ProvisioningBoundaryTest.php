<?php

use Escalated\Laravel\Contracts\TenantCatalog;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Role;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\LegacyTenantAssignment;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\TenantProvisioner;
use Escalated\Laravel\Tenancy\TenantTables;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([Events\TicketCreated::class, Events\TicketUpdated::class]);
});

it('provisions independent account defaults and role pivots without copying secrets', function () {
    config(['escalated.tenancy.enabled' => true]);
    $context = app(TenantContext::class);
    $provisioner = app(TenantProvisioner::class);
    $provisioner->provision('a');
    $context->run('a', fn () => EscalatedSettings::set('email_footer_text', 'Merchant A'));
    $provisioner->provision('b');
    $provisioner->provision('a');
    $context->run('a', function () {
        expect(EscalatedSettings::get('email_footer_text'))->toBe('Merchant A');
        expect(Role::count())->toBe(3)->and(Role::where('slug', 'admin')->sole()->permissions()->count())->toBeGreaterThan(0);
    });
    $context->run('b', function () {
        expect(EscalatedSettings::get('email_footer_text'))->toBeNull();
        expect(Role::count())->toBe(3);
    });
    expect($context->current())->toBeNull();
});

it('validates legacy assignment in a reversible transaction and refuses reassignment', function () {
    $ticket = Ticket::factory()->create();
    $assignment = app(LegacyTenantAssignment::class);
    expect($assignment->assign('a')['tickets'])->toBe(1)
        ->and($ticket->fresh()->tenant_id)->toBe('')
        ->and(config('escalated.tenancy.enabled'))->toBeFalse();
    $assignment->assign('a', true);
    expect($ticket->fresh()->tenant_id)->toBe('a');
    expect(fn () => $assignment->assign('b', true))->toThrow(RuntimeException::class, 'wholly unassigned');
    expect($ticket->fresh()->tenant_id)->toBe('a');
});

it('rolls back every table when a legacy association is inconsistent', function () {
    $ticket = Ticket::factory()->create();
    // Deliberately bypass model validation to represent corrupt legacy data.
    Escalated::db()->table(Escalated::table('tickets'))->where('id', $ticket->id)->update(['assigned_to' => 999999]);
    expect(fn () => app(LegacyTenantAssignment::class)->assign('a', true))->toThrow(RuntimeException::class, 'Invalid association');
    expect($ticket->fresh()->tenant_id)->toBe('')
        ->and(Escalated::db()->table(Escalated::table('settings'))->where('tenant_id', 'a')->count())->toBe(0);
});

it('requires a paused-writer confirmation before applying a legacy upgrade', function () {
    $this->artisan('escalated:tenant-backfill', ['tenant' => 'a', '--apply' => true])->assertFailed();
    expect(Escalated::db()->table(Escalated::table('settings'))->where('tenant_id', 'a')->count())->toBe(0);
});

it('preserves historical references to soft-deleted tickets during a legacy upgrade', function () {
    $ticket = Ticket::factory()->create();
    $author = Contact::create(['name' => 'Recipient', 'email' => 'recipient@example.test']);
    $reply = Reply::factory()->create([
        'ticket_id' => $ticket->id, 'author_type' => $author->getMorphClass(), 'author_id' => $author->id,
    ]);
    $ticket->delete();
    app(LegacyTenantAssignment::class)->assign('a', true);
    config(['escalated.tenancy.enabled' => true]);
    app(TenantContext::class)->run('a', function () use ($ticket, $reply) {
        expect(Ticket::withTrashed()->findOrFail($ticket->id)->trashed())->toBeTrue()
            ->and(Reply::findOrFail($reply->id)->tenant_id)->toBe('a');
    });
});

it('rejects malformed maintenance arguments without executing the command', function ($arguments) {
    config(['escalated.tenancy.enabled' => true]);
    $this->artisan('escalated:tenant-run', ['task' => 'escalated:wake-snoozed-tickets', '--arguments' => $arguments])->assertFailed();
})->with(['broken', '[]', 'null', '"scalar"']);

it('runs scheduled maintenance once per catalog tenant and clears the context', function () {
    config(['escalated.tenancy.enabled' => true]);
    app()->instance(TenantCatalog::class, new class implements TenantCatalog
    {
        public function tenantIds(): iterable
        {
            return ['a', 'b', 'a'];
        }
    });
    $context = app(TenantContext::class);
    foreach (['a', 'b'] as $tenant) {
        $context->run($tenant, fn () => Ticket::factory()->create(['snoozed_until' => now()->subHour()]));
    }
    $this->artisan('escalated:tenant-run', ['task' => 'escalated:wake-snoozed-tickets'])->assertSuccessful();
    expect($context->current())->toBeNull();
    foreach (['a', 'b'] as $tenant) {
        $context->run($tenant, fn () => expect(Ticket::sole()->snoozed_until)->toBeNull());
    }
    $this->artisan('escalated:tenant-run', ['task' => 'escalated:wake-snoozed-tickets', '--tenant' => ['missing']])->assertFailed();
});

it('automatically wraps cron commands and requires an explicit host catalog', function () {
    config(['escalated.tenancy.enabled' => true, 'escalated.scheduling.auto_register' => true]);
    $schedule = app(Schedule::class);
    expect($schedule->events())->not->toBeEmpty();
    foreach ($schedule->events() as $event) {
        expect($event->command)->toContain('escalated:tenant-run');
    }
    expect(fn () => app(TenantCatalog::class)->tenantIds())->toThrow(LogicException::class, 'Configure');
});

it('classifies every package table and requires tenant columns on merchant tables', function () {
    $schema = Escalated::schema();
    $prefix = config('escalated.table_prefix', 'escalated_');
    $tables = collect($schema->getTables())->pluck('name')->filter(fn ($name) => str_starts_with($name, $prefix))
        ->map(fn ($name) => substr($name, strlen($prefix)))->sort()->values()->all();
    $declared = array_merge(TenantTables::NAMES, TenantTables::PLATFORM);
    sort($declared);
    expect($tables)->toBe($declared);
    foreach (TenantTables::NAMES as $table) {
        expect($schema->hasColumn(Escalated::table($table), 'tenant_id'))->toBeTrue($table);
    }
});
