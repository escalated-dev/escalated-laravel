<?php

use Escalated\Laravel\Bridge\PluginBridge;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event as EventBus;
use Mockery\MockInterface;

/*
 * domain-model/plugin-contract.md, Cron: once a minute, every due schedule of
 * every SDK plugin is sent to the runtime as `cron {plugin, schedule}`, in
 * UTC and as no user. An unreadable schedule is logged and skipped, a failing
 * one never stops the others, and nothing runs in tenant mode.
 */

/**
 * @param  array<string, list<string>>  $schedules  plugin name => cronSchedules
 * @param  list<array{0: string, 1: string, 2: mixed}>  $calls
 */
function fakeCronBridge(array $schedules, array &$calls, ?callable $onCron = null): MockInterface
{
    $bridge = Mockery::mock(PluginBridge::class);
    $bridge->shouldReceive('isBooted')->andReturn(true)->byDefault();
    $bridge->shouldReceive('getManifests')->andReturn(array_map(
        fn ($list) => ['cronSchedules' => $list], $schedules,
    ))->byDefault();
    $bridge->shouldReceive('cron')->andReturnUsing(function (string $plugin, string $schedule) use (&$calls, $onCron) {
        $calls[] = [$plugin, $schedule, Auth::id()];
        if ($onCron) {
            $onCron($plugin, $schedule);
        }
    })->byDefault();
    app()->instance(PluginBridge::class, $bridge);
    activateSdkPlugins(...array_keys($schedules));

    return $bridge;
}

it('sends each due schedule of each SDK plugin once for the minute, in UTC, as no user', function () {
    $calls = [];
    fakeCronBridge([
        '@acme/plugin-crm' => ['* * * * *', '0 9 * * *', '5 9 * * *', '0 9 * * fri'],
        '@acme/plugin-quiet' => ['30 2 * * *'],
        '@acme/plugin-none' => [],
    ], $calls);

    // 05:00 in New York on Thursday 2026-10-29 is 09:00 UTC.
    $this->artisan('escalated:plugins:cron', ['--at' => '2026-10-29T05:00:20-04:00'])->assertSuccessful();

    expect($calls)->toBe([
        ['@acme/plugin-crm', '* * * * *', null],
        ['@acme/plugin-crm', '0 9 * * *', null],
    ]);
});

it('runs as no user even when one is signed in', function () {
    $calls = [];
    fakeCronBridge(['@acme/plugin-crm' => ['* * * * *']], $calls);
    $this->actingAs($this->createAdmin());

    $this->artisan('escalated:plugins:cron', ['--at' => '2026-10-29T09:00:00Z'])->assertSuccessful();

    expect($calls)->toBe([['@acme/plugin-crm', '* * * * *', null]]);
});

it('logs and skips a schedule it cannot read, and a failing schedule never stops the others', function () {
    $warnings = [];
    EventBus::listen(MessageLogged::class, function (MessageLogged $log) use (&$warnings) {
        if ($log->level === 'warning') {
            $warnings[] = $log->message;
        }
    });
    $calls = [];
    fakeCronBridge(
        ['@acme/plugin-crm' => ['not a schedule', '* * * * *', '*/1 * * * *'], '@acme/plugin-other' => ['* * * * *']],
        $calls,
        function (string $plugin, string $schedule) {
            if ($schedule === '* * * * *' && $plugin === '@acme/plugin-crm') {
                throw new RuntimeException('runtime went away');
            }
        },
    );

    $this->artisan('escalated:plugins:cron', ['--at' => '2026-10-29T09:00:00Z'])->assertSuccessful();

    expect(array_map(fn ($c) => [$c[0], $c[1]], $calls))->toBe([
        ['@acme/plugin-crm', '* * * * *'],
        ['@acme/plugin-crm', '*/1 * * * *'],
        ['@acme/plugin-other', '* * * * *'],
    ]);
    expect(collect($warnings)->filter(fn ($m) => str_contains($m, 'not a schedule')))->toHaveCount(1)
        ->and(collect($warnings)->filter(fn ($m) => str_contains($m, 'runtime went away')))->toHaveCount(1);
});

it('runs nothing in tenant mode, with plugins off, or before the runtime is up', function (array $config, bool $booted) {
    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }
    $calls = [];
    $bridge = fakeCronBridge(['@acme/plugin-crm' => ['* * * * *']], $calls);
    $bridge->shouldReceive('isBooted')->andReturn($booted);

    $this->artisan('escalated:plugins:cron', ['--at' => '2026-10-29T09:00:00Z'])->assertSuccessful();

    expect($calls)->toBe([]);
})->with([
    'tenant mode' => [['escalated.tenancy.enabled' => true], true],
    'plugins off' => [['escalated.plugins.enabled' => false], true],
    'SDK plugins off' => [['escalated.plugins.sdk_enabled' => false], true],
    'runtime not booted' => [[], false],
]);

it('refuses an --at that is not a time', function () {
    $calls = [];
    fakeCronBridge(['@acme/plugin-crm' => ['* * * * *']], $calls);

    $this->artisan('escalated:plugins:cron', ['--at' => 'soon'])->assertFailed();

    expect($calls)->toBe([]);
});

function scheduledPluginCron(): ?string
{
    app()->forgetInstance(Schedule::class);

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, 'escalated:plugins:cron'));

    return $event?->expression;
}

it('is scheduled every minute with auto_register, but not in tenant mode or with plugins off', function () {
    config()->set('escalated.scheduling.auto_register', true);
    expect(scheduledPluginCron())->toBe('* * * * *');

    config()->set('escalated.plugins.enabled', false);
    expect(scheduledPluginCron())->toBeNull();

    config()->set('escalated.plugins.enabled', true);
    config()->set('escalated.tenancy.enabled', true);
    expect(scheduledPluginCron())->toBeNull();
});
