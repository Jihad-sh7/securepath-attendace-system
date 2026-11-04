<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'attendanceId' => $this->attendance?->event_code, 'requestedBy' => $this->requester?->name, 'previousValues' => $this->previous_values, 'requestedValues' => $this->requested_values, 'reason' => $this->reason, 'status' => $this->status->value, 'reviewedBy' => $this->reviewer?->name, 'reviewedAt' => $this->reviewed_at?->toIso8601String(), 'reviewNote' => $this->review_note, 'createdAt' => $this->created_at?->toIso8601String()];
    }
}

