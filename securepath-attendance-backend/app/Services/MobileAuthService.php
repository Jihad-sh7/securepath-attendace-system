<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\MobileDevice;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthService
{
    public function login(string $identifier, string $secret): array
    {
        $device = MobileDevice::where('identifier', $identifier)->first();
        if (! $device || $device->status !== AccountStatus::Active || ! Hash::check($secret, $device->secret)) throw ValidationException::withMessages(['identifier' => ['Invalid device credentials.']]);
        $device->update(['last_seen_at' => now()]);
        $token = $device->createToken('mobile-kiosk', ['recognition:submit'], now()->addDays(30));
        return ['accessToken' => $token->plainTextToken, 'tokenType' => 'Bearer', 'device' => ['id' => $device->id, 'name' => $device->name, 'identifier' => $device->identifier]];
    }
}

