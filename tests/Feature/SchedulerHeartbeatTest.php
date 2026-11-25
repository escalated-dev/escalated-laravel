<?php

use Escalated\Laravel\Support\RecurringCommands;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

/*
 * SLA checks, escalation rules, automations and snooze wake-ups only happen
 * when the host schedules the package's recurring commands. escalated:check-sla
 * records a heartbeat each run; admins are warned (the `scheduler` page prop)
 * when it is missing or older than 15 minutes.
 */

beforeEach(function () {
    Cache::flush();
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('records a heartbeat when escalated:check-sla runs', function (bool $slaEnabled) {
    config(['escalated.sla.enabled' => $slaEnabled]);
    $this->freezeSecond();

    expect(RecurringCommands::lastRunAt())->toBeNull();

    $this->artisan('escalated:check-sla')->assertSuccessful();

    expect(RecurringCommands::lastRunAt()?->toIso8601String())->toBe(now()->toIso8601String());
})->with(['sla on' => true, 'sla off' => false]);

it('reports the recurring jobs as not running when there is no heartbeat', function () {
    $status = RecurringCommands::status();

    expect($status['healthy'])->toBeFalse()
        ->and($status['last_run_at'])->toBeNull()
        ->and($status['message'])->toContain('not running')
        ->and($status['message'])->toContain('escalated:check-sla has never run')
        ->and($status['message'])->toContain('ESCALATED_SCHEDULE_AUTO_REGISTER=true')
        ->and($status['commands'])->toContain(['command' => 'php artisan escalated:check-sla', 'interval' => 'every minute'])
        ->and($status['commands'])->toContain(['command' => 'php artisan escalated:evaluate-escalations', 'interval' => 'every five minutes'])
        ->and($status['commands'])->toContain(['command' => 'php artisan escalated:run-automations', 'interval' => 'every five minutes'])
        ->and($status['commands'])->toContain(['command' => 'php artisan escalated:wake-snoozed-tickets', 'interval' => 'every minute']);
});

it('is healthy while the heartbeat is under 15 minutes old', function () {
    $this->freezeSecond();
    $this->artisan('escalated:check-sla')->assertSuccessful();

    $this->travel(14)->minutes();
    expect(RecurringCommands::status())->toMatchArray(['healthy' => true, 'message' => '']);

    $this->travel(2)->minutes();
    $status = RecurringCommands::status();
    expect($status['healthy'])->toBeFalse()
        ->and($status['last_run_at'])->not->toBeNull()
        ->and($status['message'])->toContain('escalated:check-sla last ran 16 minutes ago');
});

it('points at schedule:run when auto-registration is already on', function () {
    config(['escalated.scheduling.auto_register' => true]);

    expect(RecurringCommands::status()['message'])
        ->toContain('scheduling.auto_register is on')
        ->toContain('php artisan schedule:run');
});

it('sends admins the scheduler warning on settings and reports', function (string $route, string $page) {
    $admin = $this->createAdmin();

    $this->actingAs($admin)->get(route($route))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p
            ->component($page, false)
            ->where('scheduler.healthy', false)
            ->where('scheduler.last_run_at', null)
            ->where('scheduler.message', fn ($message) => str_contains($message, 'not running'))
            ->has('scheduler.commands.0', fn (AssertableInertia $command) => $command
                ->where('command', 'php artisan escalated:check-sla')
                ->where('interval', 'every minute')));

    $this->artisan('escalated:check-sla')->assertSuccessful();

    $this->actingAs($admin)->get(route($route))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('scheduler.healthy', true)->etc());
})->with([
    'settings' => ['escalated.admin.settings', 'Escalated/Admin/Settings'],
    'reports' => ['escalated.admin.reports', 'Escalated/Admin/Reports'],
    'reports dashboard' => ['escalated.admin.reports.dashboard', 'Escalated/Admin/Reports/Dashboard'],
]);
