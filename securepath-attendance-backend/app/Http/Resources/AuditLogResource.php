<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => 'AUD-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT), 'timestamp' => $this->created_at?->format('Y-m-d H:i'), 'user' => $this->user?->name ?? 'System', 'action' => $this->action, 'resource' => trim(($this->resource_type ?? '').' '.($this->resource_id ?? '')), 'resourceType' => $this->resource_type, 'resourceId' => $this->resource_id, 'previousValue' => $this->old_values, 'newValue' => $this->new_values, 'result' => ucfirst($this->result), 'device' => $this->ip_address, 'userAgent' => $this->user_agent];
    }
}

