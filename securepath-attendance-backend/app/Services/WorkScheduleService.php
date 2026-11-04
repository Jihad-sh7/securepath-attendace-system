<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Schedule;
use Carbon\Carbon;

class WorkScheduleService
{
    public function scheduleFor(Person $person, Carbon $date): ?Schedule
    {
        $dateString = $date->toDateString();
        $activeOnDate = fn ($query) => $query
            ->where('schedules.status', 'active')
            ->where(fn ($q) => $q->whereNull('schedules.effective_from')->orWhereDate('schedules.effective_from', '<=', $dateString))
            ->where(fn ($q) => $q->whereNull('schedules.effective_until')->orWhereDate('schedules.effective_until', '>=', $dateString));

        $personal = $person->schedules()
            ->where($activeOnDate)
            ->where(fn ($q) => $q->whereNull('person_schedule.effective_from')->orWhereDate('person_schedule.effective_from', '<=', $dateString))
            ->where(fn ($q) => $q->whereNull('person_schedule.effective_until')->orWhereDate('person_schedule.effective_until', '>=', $dateString))
            ->with('days')
            ->first();

        return $personal ?? $person->department?->schedules()->where($activeOnDate)->with('days')->first();
    }

    public function boundsFor(Person $person, Carbon $date, ?string $startSnapshot = null, ?string $endSnapshot = null): ?array
    {
        $schedule = $this->scheduleFor($person, $date);
        if (! $schedule) return null;

        $day = $schedule->days->firstWhere('day_of_week', $date->dayOfWeekIso);
        if ($day && ! $day->is_working_day) return null;

        $startTime = $startSnapshot ?: ($day?->start_time ?: $schedule->start_time);
        $endTime = $endSnapshot ?: ($day?->end_time ?: $schedule->end_time);
        if (! $startTime || ! $endTime) return null;

        $start = Carbon::parse($date->toDateString().' '.$startTime, $date->timezone);
        $end = Carbon::parse($date->toDateString().' '.$endTime, $date->timezone);
        if ($end->lessThanOrEqualTo($start)) $end->addDay();

        return ['schedule' => $schedule, 'start' => $start, 'end' => $end];
    }
}
