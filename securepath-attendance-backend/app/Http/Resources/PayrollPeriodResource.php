<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'year' => $this->year, 'month' => $this->month, 'label' => $this->starts_on?->format('F Y'), 'startsOn' => $this->starts_on?->toDateString(), 'endsOn' => $this->ends_on?->toDateString(), 'status' => ucfirst($this->status), 'currency' => $this->currency, 'calculatedAt' => $this->calculated_at?->toIso8601String(), 'entryCount' => $this->entries_count ?? null];
    }
}
