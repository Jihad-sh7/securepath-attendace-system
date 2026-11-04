<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $duration = $this->worked_minutes === null ? ($this->check_in_at && ! $this->check_out_at ? 'In progress' : '0h') : sprintf('%dh %02dm', intdiv($this->worked_minutes, 60), $this->worked_minutes % 60);
        $regularMinutes = 0;
        $overtimeMinutes = 0;
        if ($this->check_in_at && $this->check_out_at && $this->scheduled_start && $this->scheduled_end) {
            $scheduledStart = \Carbon\Carbon::parse($this->attendance_date->toDateString().' '.$this->scheduled_start);
            $scheduledEnd = \Carbon\Carbon::parse($this->attendance_date->toDateString().' '.$this->scheduled_end);
            if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) $scheduledEnd->addDay();
            $overlapStart = $this->check_in_at->greaterThan($scheduledStart) ? $this->check_in_at : $scheduledStart;
            $overlapEnd = $this->check_out_at->lessThan($scheduledEnd) ? $this->check_out_at : $scheduledEnd;
            $regularMinutes = $overlapEnd->greaterThan($overlapStart) ? (int) $overlapStart->diffInMinutes($overlapEnd) : 0;
            $overtimeMinutes = max(0, (int) $this->check_in_at->diffInMinutes($this->check_out_at) - $regularMinutes);
        }

        return [
            'id' => $this->event_code,
            'personId' => $this->person?->person_code,
            'personName' => $this->person?->full_name ?? 'Unknown person',
            'department' => $this->person?->department?->name ?? 'Unassigned',
            'date' => $this->attendance_date?->toDateString(),
            'scheduledStart' => $this->scheduled_start,
            'scheduledEnd' => $this->scheduled_end,
            'checkIn' => $this->check_in_at?->format('H:i') ?? '',
            'checkOut' => $this->check_out_at?->format('H:i') ?? '',
            'checkInAt' => $this->check_in_at?->toIso8601String(),
            'checkOutAt' => $this->check_out_at?->toIso8601String(),
            'checkInSource' => $this->check_in_source?->value,
            'checkOutSource' => $this->check_out_source?->value,
            'checkoutSource' => $this->check_out_source?->value,
            'checkedInByUserId' => $this->checked_in_by_user_id,
            'checkedInBy' => $this->whenLoaded('checkedInBy', fn () => $this->checkedInBy?->name),
            'checkedOutByUserId' => $this->checked_out_by_user_id,
            'checkedOutBy' => $this->whenLoaded('checkedOutBy', fn () => $this->checkedOutBy?->name),
            'autoCheckoutReason' => $this->auto_checkout_reason,
            'duration' => $duration,
            'status' => str_replace('_', ' ', ucfirst($this->status->value)),
            'minutesLate' => $this->minutes_late,
            'workedMinutes' => $this->worked_minutes,
            'regularMinutes' => $regularMinutes,
            'overtimeMinutes' => $overtimeMinutes,
            'method' => str_replace('_', ' ', ucwords($this->verification_method->value, '_')),
            'verificationMethod' => $this->verification_method->value,
            'confidence' => $this->recognition_confidence === null ? 0 : round((float) $this->recognition_confidence * 100, 1),
            'liveness' => $this->liveness_result ? ucfirst($this->liveness_result->value) : 'N/A',
            'source' => $this->source->value,
            'sourceIdentifier' => $this->source_identifier,
            'location' => $this->location,
            'note' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'events' => $this->whenLoaded('events', fn () => AttendanceEventResource::collection($this->events)->resolve()),
        ];
    }
}
