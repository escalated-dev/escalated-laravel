<?php

use Carbon\Carbon;
use Escalated\Laravel\Models\BusinessSchedule;
use Escalated\Laravel\Services\BusinessHoursCalculator;

/*
 * Hours are wall-clock times in the schedule's zone, counted in real elapsed
 * time. A window belongs to the local date it opens on.
 */

function bhSchedule(array $days, string $timezone = 'UTC', array $holidays = []): BusinessSchedule
{
    $schedule = BusinessSchedule::create([
        'name' => 'Schedule '.uniqid(),
        'timezone' => $timezone,
        'schedule' => $days,
    ]);
    foreach ($holidays as $holiday) {
        $schedule->holidays()->create($holiday + ['name' => 'Holiday', 'recurring' => false]);
    }

    return $schedule->fresh();
}

function bhWeekdays(string $start = '09:00', string $end = '17:00'): array
{
    $hours = ['start' => $start, 'end' => $end];

    return [
        'monday' => $hours, 'tuesday' => $hours, 'wednesday' => $hours, 'thursday' => $hours, 'friday' => $hours,
        'saturday' => ['start' => '', 'end' => ''], 'sunday' => ['start' => '', 'end' => ''],
    ];
}

function bhEveryDay(string $start, string $end): array
{
    return array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], ['start' => $start, 'end' => $end]);
}

function bhAdd(string $from, float $hours, BusinessSchedule $schedule): string
{
    return (new BusinessHoursCalculator)
        ->addBusinessHours(Carbon::parse($from, 'UTC'), $hours, $schedule)
        ->copy()->setTimezone('UTC')->format('Y-m-d H:i');
}

// 2026-10-23 is a Friday; 2026-10-26 a Monday.

it('adds hours inside one window', function () {
    expect(bhAdd('2026-10-26 10:00', 2, bhSchedule(bhWeekdays())))->toBe('2026-10-26 12:00');
});

it('skips the weekend', function () {
    expect(bhAdd('2026-10-23 16:00', 2, bhSchedule(bhWeekdays())))->toBe('2026-10-26 10:00');
});

it('starts at opening time when created before hours', function () {
    expect(bhAdd('2026-10-26 07:00', 1, bhSchedule(bhWeekdays())))->toBe('2026-10-26 10:00');
});

it('rolls over to the next open day when created after hours', function () {
    expect(bhAdd('2026-10-26 18:00', 1, bhSchedule(bhWeekdays())))->toBe('2026-10-27 10:00');
});

it('skips a one-off holiday', function () {
    $schedule = bhSchedule(bhWeekdays(), 'UTC', [['date' => '2026-10-27']]);

    expect(bhAdd('2026-10-26 16:00', 2, $schedule))->toBe('2026-10-28 10:00');
});

it('skips a recurring holiday in a later year', function () {
    $schedule = bhSchedule(bhWeekdays(), 'UTC', [['date' => '2020-12-25', 'recurring' => true]]);

    // Thursday the 24th, Christmas Friday, then the weekend.
    expect(bhAdd('2026-12-24 16:00', 2, $schedule))->toBe('2026-12-28 10:00');
});

it('does not treat a one-off holiday as recurring', function () {
    $schedule = bhSchedule(bhWeekdays(), 'UTC', [['date' => '2025-10-27']]);

    expect(bhAdd('2026-10-26 16:00', 2, $schedule))->toBe('2026-10-27 10:00');
});

it('counts an overnight window across midnight', function () {
    $schedule = bhSchedule(bhEveryDay('22:00', '06:00'));

    expect(bhAdd('2026-10-26 23:00', 2, $schedule))->toBe('2026-10-27 01:00')
        // Monday 05:00 is still inside Sunday's window.
        ->and(bhAdd('2026-10-26 05:00', 2, $schedule))->toBe('2026-10-26 23:00');
});

it('closes the whole overnight window that opens on a holiday', function () {
    $schedule = bhSchedule(bhEveryDay('22:00', '06:00'), 'UTC', [['date' => '2026-10-27']]);

    // Monday's window gives 7 hours; Tuesday's (22:00 to Wednesday 06:00) is closed.
    expect(bhAdd('2026-10-26 23:00', 8, $schedule))->toBe('2026-10-28 23:00');
});

it('reads 24:00 as the next midnight', function () {
    $schedule = bhSchedule(bhWeekdays('09:00', '24:00'));

    expect(bhAdd('2026-10-26 23:00', 2, $schedule))->toBe('2026-10-27 10:00');
});

it('treats an equal start and end as closed', function () {
    $days = bhWeekdays();
    $days['monday'] = ['start' => '09:00', 'end' => '09:00'];

    expect(bhAdd('2026-10-26 08:00', 1, bhSchedule($days)))->toBe('2026-10-27 10:00');
});

it('reads hours in the schedule time zone', function () {
    // 12:00 UTC is 08:00 in New York (EDT).
    expect(bhAdd('2026-10-26 12:00', 1, bhSchedule(bhWeekdays(), 'America/New_York')))->toBe('2026-10-26 14:00');
});

it('matches holidays on the local date', function () {
    // Tokyo is UTC+9: Monday 08:00 local is Sunday 23:00 UTC.
    $schedule = bhSchedule(bhWeekdays('08:00', '10:00'), 'Asia/Tokyo', [['date' => '2026-10-26']]);

    expect(bhAdd('2026-10-25 22:00', 1, $schedule))->toBe('2026-10-27 00:00');
});

it('counts real time across a spring-forward change', function () {
    // 2026-03-08: New York jumps from 02:00 EST to 03:00 EDT, so 01:00 to 05:00 is 3 hours.
    $schedule = bhSchedule(['sunday' => ['start' => '01:00', 'end' => '05:00']], 'America/New_York');

    expect(bhAdd('2026-03-08 06:00', 3, $schedule))->toBe('2026-03-08 09:00')
        // The fourth hour lands in the next Sunday's window (01:00 EDT = 05:00 UTC).
        ->and(bhAdd('2026-03-08 06:00', 4, $schedule))->toBe('2026-03-15 06:00');
});

it('opens at the change when the opening time is skipped by spring-forward', function () {
    $schedule = bhSchedule(['sunday' => ['start' => '02:30', 'end' => '05:00']], 'America/New_York');

    // 02:30 does not exist on 2026-03-08; the window opens at 03:00 EDT (07:00 UTC).
    expect(bhAdd('2026-03-08 05:00', 1, $schedule))->toBe('2026-03-08 08:00');
});

it('counts real time across a fall-back change', function () {
    // 2026-11-01: New York falls back from 02:00 EDT to 01:00 EST, so 00:00 to 04:00 is 5 hours.
    $schedule = bhSchedule(['sunday' => ['start' => '00:00', 'end' => '04:00']], 'America/New_York');

    expect(bhAdd('2026-11-01 04:00', 5, $schedule))->toBe('2026-11-01 09:00');
});

it('falls back to calendar time when no day is open', function () {
    $schedule = bhSchedule(['monday' => ['start' => '', 'end' => '']]);

    expect(bhAdd('2026-10-24 10:00', 5, $schedule))->toBe('2026-10-24 15:00');
});

it('uses UTC for an unknown time zone', function () {
    expect(bhAdd('2026-10-26 10:00', 2, bhSchedule(bhWeekdays(), 'Mars/Olympus_Mons')))->toBe('2026-10-26 12:00');
});

it('honours a legacy enabled flag', function () {
    $days = bhWeekdays();
    $days['monday']['enabled'] = false;

    expect(bhAdd('2026-10-26 08:00', 1, bhSchedule($days)))->toBe('2026-10-27 10:00');
});

it('handles fractional hours', function () {
    expect(bhAdd('2026-10-26 16:45', 0.5, bhSchedule(bhWeekdays())))->toBe('2026-10-27 09:15');
});

it('returns the start for zero hours', function () {
    expect(bhAdd('2026-10-24 10:00', 0, bhSchedule(bhWeekdays())))->toBe('2026-10-24 10:00');
});

it('returns the deadline in the time zone of the start', function () {
    $due = (new BusinessHoursCalculator)->addBusinessHours(
        Carbon::parse('2026-10-26 12:00', 'Europe/London'), 1, bhSchedule(bhWeekdays(), 'America/New_York')
    );

    expect($due->getTimezone()->getName())->toBe('Europe/London');
});

it('tells whether an instant is within business hours', function () {
    $calculator = new BusinessHoursCalculator;
    $weekdays = bhSchedule(bhWeekdays(), 'UTC', [['date' => '2026-10-27']]);
    $overnight = bhSchedule(bhEveryDay('22:00', '06:00'));

    expect($calculator->isWithinBusinessHours(Carbon::parse('2026-10-26 10:00', 'UTC'), $weekdays))->toBeTrue()
        ->and($calculator->isWithinBusinessHours(Carbon::parse('2026-10-26 17:00', 'UTC'), $weekdays))->toBeFalse()
        ->and($calculator->isWithinBusinessHours(Carbon::parse('2026-10-24 10:00', 'UTC'), $weekdays))->toBeFalse()
        ->and($calculator->isWithinBusinessHours(Carbon::parse('2026-10-27 10:00', 'UTC'), $weekdays))->toBeFalse()
        ->and($calculator->isWithinBusinessHours(Carbon::parse('2026-10-27 01:00', 'UTC'), $overnight))->toBeTrue()
        ->and($calculator->isWithinBusinessHours(Carbon::parse('2026-10-27 12:00', 'UTC'), $overnight))->toBeFalse();
});
