<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'timestamp' => $this->occurred_at?->toIso8601String(),
            'source' => $this->source->value,
            'performedByUserId' => $this->performer ? 'USR-'.str_pad((string) $this->performer->id, 5, '0', STR_PAD_LEFT) : null,
            'performedBy' => $this->performer?->name,
            'note' => $this->note,
            'metadata' => $this->metadata,
        ];
    }
}

