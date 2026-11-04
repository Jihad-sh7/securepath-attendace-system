<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditService
{
    public function record(string $action, Model|string|null $resource = null, ?array $oldValues = null, ?array $newValues = null, string $result = 'success', ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();
        $sensitive = ['password', 'password_confirmation', 'secret', 'secure_reference', 'token'];

        return AuditLog::create([
            'user_id' => $userId ?? (auth()->user() instanceof \App\Models\User ? auth()->id() : null),
            'action' => $action,
            'resource_type' => $resource instanceof Model ? class_basename($resource) : ($resource ? 'System' : null),
            'resource_id' => $resource instanceof Model ? (string) $resource->getRouteKey() : $resource,
            'old_values' => $oldValues ? Arr::except($oldValues, $sensitive) : null,
            'new_values' => $newValues ? Arr::except($newValues, $sensitive) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'result' => $result,
        ]);
    }
}

