<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => 'USR-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT),
            'databaseId' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'title' => $this->title ?? match ($this->role->value) { 'super_admin' => 'Super Admin', 'admin' => 'Admin', default => 'Employee' },
            'status' => ucfirst($this->status->value),
            'twoFactor' => $this->two_factor_enabled ? 'Enabled' : 'Disabled',
            'lastLogin' => $this->last_login_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toDateString(),
            'avatarUrl' => $this->avatar_path ? route('files.user-avatar', ['user' => $this->id]) : null,
            'permissions' => $this->isSuperAdmin() ? [] : $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()->all(), []),
            'personId' => $this->person?->person_code,
        ];
    }
}
