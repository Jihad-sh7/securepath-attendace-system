<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->alert_code, 'type' => str_replace('_', ' ', ucwords($this->type, '_')), 'severity' => ucfirst($this->severity->value), 'status' => ucfirst($this->status->value), 'title' => $this->title, 'message' => $this->message, 'notes' => $this->message, 'personId' => $this->person?->person_code, 'attendanceId' => $this->attendance?->event_code, 'recognitionEventId' => $this->recognitionEvent?->recognition_code, 'assignedTo' => $this->assignee?->name, 'related' => $this->person?->person_code ?? $this->attendance?->event_code ?? $this->recognitionEvent?->source_device_identifier, 'resolvedAt' => $this->resolved_at?->toIso8601String(), 'timestamp' => $this->created_at?->format('Y-m-d H:i'), 'metadata' => $this->metadata];
    }
}
