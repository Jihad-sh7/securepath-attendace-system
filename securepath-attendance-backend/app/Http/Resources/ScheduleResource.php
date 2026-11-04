<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->code,
            'code' => $this->code,
            'name' => $this->name,
            'type' => ucfirst($this->type),
            'startTime' => substr((string) $this->start_time, 0, 5),
            'endTime' => substr((string) $this->end_time, 0, 5),
            'gracePeriod' => $this->grace_period_minutes,
            'gracePeriodMinutes' => $this->grace_period_minutes,
            'breakDurationMinutes' => $this->break_duration_minutes,
            'breakWindow' => $this->break_window,
            'effectiveFrom' => $this->effective_from?->toDateString(),
            'effectiveUntil' => $this->effective_until?->toDateString(),
            'status' => ucfirst($this->status),
            'days' => $this->whenLoaded('days', fn () => $this->days->map(fn ($day) => ['dayOfWeek' => $day->day_of_week, 'isWorkingDay' => $day->is_working_day])->values()),
            'assignedTo' => collect($this->whenLoaded('departments', fn () => $this->departments->pluck('name'), []))->merge($this->whenLoaded('people', fn () => $this->people->pluck('full_name'), []))->values(),
            'conflicts' => 0,
        ];
    }
}

