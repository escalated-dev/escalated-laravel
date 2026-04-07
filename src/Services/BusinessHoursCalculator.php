<?php

namespace Escalated\Laravel\Services;

use Carbon\Carbon;
use Escalated\Laravel\Models\BusinessSchedule;
use Illuminate\Database\Eloquent\Collection;

class BusinessHoursCalculator
{
    /**
     * Check if a given datetime falls within business hours.
     */
    public function isWithinBusinessHours(Carbon $dateTime, BusinessSchedule $schedule): bool
    {
        $dateTime = $dateTime->copy()->setTimezone($schedule->timezone);

        if ($this->isHoliday($dateTime, $schedule)) {
            return false;
        }

        $daySchedule = $this->getDaySchedule($dateTime, $schedule);

        if (empty($daySchedule) || empty($daySchedule['start']) || empty($daySchedule['end'])) {
            return false;
        }

        $time = $dateTime->format('H:i');

        return $time >= $daySchedule['start'] && $time < $daySchedule['end'];
    }

    /**
     * Add business hours to a start time and return the resulting datetime.
     */
    public function addBusinessHours(Carbon $start, float $hours, BusinessSchedule $schedule): Carbon
    {
        $current = $start->copy()->setTimezone($schedule->timezone);
        $remainingMinutes = $hours * 60;
        $maxIterations = 365; // Safety limit

        while ($remainingMinutes > 0 && $maxIterations-- > 0) {
            if ($this->isHoliday($current, $schedule)) {
                $current->addDay()->startOfDay();

                continue;
            }

            $daySchedule = $this->getDaySchedule($current, $schedule);

            if (empty($daySchedule) || empty($daySchedule['start']) || empty($daySchedule['end'])) {
                $current->addDay()->startOfDay();

                continue;
            }

            $dayStart = $current->copy()->setTimeFromTimeString($daySchedule['start']);
            $dayEnd = $current->copy()->setTimeFromTimeString($daySchedule['end']);

            // If current time is before business hours start, jump to start
            if ($current->lt($dayStart)) {
                $current = $dayStart->copy();
            }

            // If current time is at or after business hours end, jump to next day
            if ($current->gte($dayEnd)) {
                $current->addDay()->startOfDay();

                continue;
            }

            $availableMinutes = $current->diffInMinutes($dayEnd);

            if ($remainingMinutes <= $availableMinutes) {
                $current->addMinutes((int) $remainingMinutes);
                $remainingMinutes = 0;
            } else {
                $remainingMinutes -= $availableMinutes;
                $current->addDay()->startOfDay();
            }
        }

        return $current->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Get the schedule for a specific day of the week.
     */
    protected function getDaySchedule(Carbon $dateTime, BusinessSchedule $schedule): ?array
    {
        $dayName = strtolower($dateTime->format('l')); // monday, tuesday, etc.
        $scheduleData = $schedule->schedule ?? [];

        return $scheduleData[$dayName] ?? null;
    }

    /**
     * Check if a given date is a holiday.
     */
    protected function isHoliday(Carbon $dateTime, BusinessSchedule $schedule): bool
    {
        $holidays = $schedule->holidays ?? collect();

        if ($holidays instanceof Collection && ! $holidays->isNotEmpty()) {
            // Load the relationship if not already loaded
            if (! $schedule->relationLoaded('holidays')) {
                $schedule->load('holidays');
                $holidays = $schedule->holidays;
            }
        }

        foreach ($holidays as $holiday) {
            if ($holiday->recurring) {
                // Match month and day only
                if ($dateTime->month === $holiday->date->month && $dateTime->day === $holiday->date->day) {
                    return true;
                }
            } else {
                if ($dateTime->isSameDay($holiday->date)) {
                    return true;
                }
            }
        }

        return false;
    }
}
