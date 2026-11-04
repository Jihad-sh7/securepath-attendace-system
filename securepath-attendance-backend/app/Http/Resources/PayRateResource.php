<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'departmentId' => $this->department?->code, 'department' => $this->department?->name, 'roleId' => $this->role?->code, 'role' => $this->role?->name, 'hourlyRate' => $this->hourly_rate, 'currency' => $this->currency, 'effectiveFrom' => $this->effective_from?->toDateString(), 'effectiveUntil' => $this->effective_until?->toDateString(), 'createdAt' => $this->created_at?->toIso8601String()];
    }
}
