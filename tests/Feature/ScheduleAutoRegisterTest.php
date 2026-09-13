<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The escalated:* commands on a freshly resolved Schedule, keyed by command
 * name, with their cron expressions.
 *
 * @return array<string, string>
 */
function escalatedScheduledCommands(): array
{
    // The package hooks the Schedule when it is resolved, which is when
    // schedule:run and schedule:list build it. Resolve a new one so the
    // config set by each test is what gets read.
    app()->forgetInstance(Schedule::class);

    return collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'escalated:'))
        ->mapWithKeys(fn (Event $event) => [
            preg_replace('/^.*?(escalated:[\w:-]+).*$/', '$1', (string) $event->command) => $event->expression,
        ])
        ->all();
}

it('schedules nothing when auto_register is off', function () {
    config()->set('escalated.scheduling.auto_register', false);

    expect(escalatedScheduledCommands())->toBe([]);
});

it('schedules the maintenance commands when auto_register is on', function () {
    config()->set('escalated.scheduling.auto_register', true);

    $scheduled = escalatedScheduledCommands();

    expect($scheduled)->toMatchArray([
        'escalated:check-sla' => '* * * * *',
        'escalated:evaluate-escalations' => '*/5 * * * *',
        'escalated:run-automations' => '*/5 * * * *',
        'escalated:process-delayed-actions' => '* * * * *',
        'escalated:wake-snoozed-tickets' => '* * * * *',
        'escalated:close-resolved' => '0 0 * * *',
        'escalated:purge-activities' => '0 0 * * 0',
    ]);

    // Off by default, so not scheduled by default.
    expect($scheduled)->not->toHaveKeys([
        'escalated:close-idle-chats',
        'escalated:cleanup-abandoned-chats',
        'escalated:newsletters:dispatch',
        'escalated:poll-imap',
    ]);
});

it('schedules the commands for optional features once those features are on', function () {
    config()->set('escalated.scheduling.auto_register', true);
    config()->set('escalated.chat.enabled', true);
    config()->set('escalated.enable_newsletters', true);
    config()->set('escalated.inbound_email.enabled', true);
    config()->set('escalated.inbound_email.adapter', 'imap');

    expect(escalatedScheduledCommands())->toMatchArray([
        'escalated:close-idle-chats' => '* * * * *',
        'escalated:cleanup-abandoned-chats' => '* * * * *',
        'escalated:newsletters:dispatch' => '* * * * *',
        'escalated:poll-imap' => '* * * * *',
    ]);
});
