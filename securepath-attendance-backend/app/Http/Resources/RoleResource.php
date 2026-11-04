<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->code, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'status' => ucfirst($this->status), 'peopleCount' => $this->people_count ?? 0];
    }
}
