<?php

namespace Escalated\Laravel\Services;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Escalated\Laravel\Models\BusinessSchedule;
use Throwable;

/**
 * Counts business time against a BusinessSchedule and its holidays.
 *
 * Opening hours are wall-clock times in the schedule's time zone (UTC when the
 * zone is unknown), but time is counted in real elapsed seconds, so a window on
 * a daylight-saving change day is an hour shorter or longer than its
 * wall-clock span, and an opening time skipped by spring-forward opens at the
 * change. A window belongs to the local date it opens on: a holiday (one-off,
 * or `recurring` every year on its month and day) on that date closes it,
 * overnight part included. `24:00` is the next midnight; an end before the
 * start is an overnight window; an equal start and end is closed, as is a day
 * with `enabled: false`. A schedule with no open day counts calendar time, so
 * a policy never loses its deadline.
 */
class BusinessHoursCalculator
{
    /** A schedule whose windows are all closed by holidays for ten years has no hours. */
    private const MAX_SCAN_DAYS = 3660;

    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Whether a given instant falls within one of the schedule's windows.
     */
    public function isWithinBusinessHours(Carbon $dateTime, BusinessSchedule $schedule): bool
    {
        $zone = $this->zone($schedule);
        $holidays = $this->holidays($schedule);
        $instant = $dateTime->getTimestamp();
        $today = (new DateTimeImmutable('@'.$instant))->setTimezone($zone)->setTime(0, 0);

        foreach ([$today->modify('-1 day'), $today] as $day) {
            $window = $this->windowOn($day, $schedule, $zone, $holidays);
            if ($window && $instant >= $window[0] && $instant < $window[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * The instant $hours of business time after $start, in $start's time zone.
     */
    public function addBusinessHours(Carbon $start, float $hours, BusinessSchedule $schedule): Carbon
    {
        $remaining = (int) round($hours * 3600);
        $result = $start->copy();

        if ($remaining <= 0) {
            return $result;
        }

        if (! $this->hasOpenDay($schedule)) {
            return $result->addSeconds($remaining);
        }

        $zone = $this->zone($schedule);
        $holidays = $this->holidays($schedule);
        $cursor = $start->getTimestamp();
        // Start a day early: an overnight window opened yesterday may still be open.
        $day = (new DateTimeImmutable('@'.$cursor))->setTimezone($zone)->setTime(0, 0)->modify('-1 day');

        for ($i = 0; $i < self::MAX_SCAN_DAYS; $i++) {
            $window = $this->windowOn($day, $schedule, $zone, $holidays);
            if ($window && $window[1] > $cursor) {
                $from = max($window[0], $cursor);
                $available = $window[1] - $from;
                if ($remaining <= $available) {
                    return $result->setTimestamp($from + $remaining);
                }
                $remaining -= $available;
                $cursor = $window[1];
            }
            $day = $day->modify('+1 day')->setTime(0, 0);
        }

        return $start->copy()->addSeconds((int) round($hours * 3600));
    }

    /**
     * The window that opens on the local date $day, as [start, end) Unix
     * timestamps, or null when that date is closed or a holiday.
     *
     * @param  array<int, array{date: string, recurring: bool}>  $holidays
     * @return array{0: int, 1: int}|null
     */
    protected function windowOn(DateTimeImmutable $day, BusinessSchedule $schedule, DateTimeZone $zone, array $holidays): ?array
    {
        $bounds = $this->bounds($this->getDaySchedule($day, $schedule));
        if ($bounds === null || $this->isHoliday($day, $holidays)) {
            return null;
        }

        [$startMinutes, $endMinutes] = $bounds;
        $start = $this->wallClock($day, $startMinutes, $zone);
        $endDay = $endMinutes <= $startMinutes ? $day->modify('+1 day') : $day;
        $end = $this->wallClock($endDay, $endMinutes, $zone);

        return $end > $start ? [$start, $end] : null;
    }

    /**
     * The hours stored for $day's weekday.
     */
    protected function getDaySchedule(DateTimeImmutable $day, BusinessSchedule $schedule): mixed
    {
        $days = $schedule->schedule ?? [];

        return is_array($days) ? ($days[self::WEEKDAYS[(int) $day->format('N') - 1]] ?? null) : null;
    }

    protected function hasOpenDay(BusinessSchedule $schedule): bool
    {
        $days = is_array($schedule->schedule) ? $schedule->schedule : [];

        foreach (self::WEEKDAYS as $key) {
            if ($this->bounds($days[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * A day's start and end in minutes after midnight, or null when closed.
     *
     * @return array{0: int, 1: int}|null
     */
    protected function bounds(mixed $hours): ?array
    {
        if (! is_array($hours) || (array_key_exists('enabled', $hours) && ! $hours['enabled'])) {
            return null;
        }

        $start = $this->clockMinutes($hours['start'] ?? null);
        $end = $this->clockMinutes($hours['end'] ?? null);

        if ($start === null || $end === null || $start >= 1440 || $start === $end) {
            return null;
        }

        return [$start, $end];
    }

    /** Parses "HH:MM", 00:00 to 24:00. */
    protected function clockMinutes(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^\s*(\d{1,2}):(\d{2})\s*$/', $value, $match)) {
            return null;
        }

        $h = (int) $match[1];
        $m = (int) $match[2];

        if ($h > 24 || $m > 59 || ($h === 24 && $m !== 0)) {
            return null;
        }

        return $h * 60 + $m;
    }

    /**
     * The instant the clock in $zone reads $minutes past midnight on the local
     * date $day. 1440 is the next midnight. A wall time skipped by a
     * daylight-saving change resolves to the moment the clocks go forward.
     */
    protected function wallClock(DateTimeImmutable $day, int $minutes, DateTimeZone $zone): int
    {
        $date = $minutes >= 1440 ? $day->modify('+1 day') : $day;
        $minutes %= 1440;
        $wanted = sprintf('%s %02d:%02d', $date->format('Y-m-d'), intdiv($minutes, 60), $minutes % 60);

        $instant = new DateTimeImmutable($wanted, $zone);
        if ($instant->format('Y-m-d H:i') === $wanted) {
            return $instant->getTimestamp();
        }

        // In a gap PHP moves the wall time forward by the size of the jump;
        // the window opens at the change itself.
        $timestamp = $instant->getTimestamp();
        $change = $timestamp;
        foreach ($zone->getTransitions($timestamp - 86400, $timestamp) ?: [] as $transition) {
            if ($transition['ts'] <= $timestamp) {
                $change = $transition['ts'];
            }
        }

        return $change;
    }

    /**
     * @param  array<int, array{date: string, recurring: bool}>  $holidays
     */
    protected function isHoliday(DateTimeImmutable $day, array $holidays): bool
    {
        $date = $day->format('Y-m-d');

        foreach ($holidays as $holiday) {
            if ($holiday['recurring'] ? substr($holiday['date'], 5) === substr($date, 5) : $holiday['date'] === $date) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{date: string, recurring: bool}>
     */
    protected function holidays(BusinessSchedule $schedule): array
    {
        if (! $schedule->exists && ! $schedule->relationLoaded('holidays')) {
            return [];
        }

        $holidays = [];
        foreach ($schedule->holidays as $holiday) {
            if ($holiday->date) {
                $holidays[] = ['date' => $holiday->date->format('Y-m-d'), 'recurring' => (bool) $holiday->recurring];
            }
        }

        return $holidays;
    }

    protected function zone(BusinessSchedule $schedule): DateTimeZone
    {
        try {
            return new DateTimeZone((string) ($schedule->timezone ?: 'UTC'));
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
