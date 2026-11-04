<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->request_code, 'personId' => $this->person?->person_code, 'personName' => $this->person?->full_name, 'startDate' => $this->start_date?->toDateString(), 'endDate' => $this->end_date?->toDateString(), 'date' => $this->start_date?->toDateString(), 'type' => $this->type, 'reason' => $this->reason, 'message' => $this->reason, 'status' => ucfirst($this->status->value), 'reviewedBy' => $this->reviewer?->name, 'reviewedAt' => $this->reviewed_at?->toIso8601String(), 'reviewNote' => $this->review_note];
    }
}

