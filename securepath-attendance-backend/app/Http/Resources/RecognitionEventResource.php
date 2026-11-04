<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecognitionEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->recognition_code, 'eventUuid' => $this->event_uuid, 'personId' => $this->person?->person_code, 'name' => $this->person?->full_name ?? 'Unknown Face',
            'attendanceId' => $this->attendance?->event_code, 'confidence' => $this->confidence === null ? 0 : round((float) $this->confidence * 100, 1),
            'liveness' => ucfirst($this->liveness_result->value), 'recognitionStatus' => $this->recognition_status->value, 'verificationStatus' => $this->verification_status->value,
            'status' => str_replace('_', ' ', ucwords($this->verification_status->value, '_')), 'deviceIdentifier' => $this->source_device_identifier, 'cameraId' => $this->source_device_identifier,
            'location' => $this->location, 'occurredAt' => $this->occurred_at?->toIso8601String(), 'timestamp' => $this->occurred_at?->format('h:i:s A'), 'metadata' => $this->metadata,
        ];
    }
}

