<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly AuditService $audit) {}

    public function login(string $email, string $password, string $deviceName = 'react-dashboard'): array
    {
        $user = User::with('permissions')->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->audit->record('auth.login_failed', $email, result: 'failed', userId: $user?->id);
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if ($user->status !== AccountStatus::Active) {
            $this->audit->record('auth.login_blocked', $user, result: 'failed', userId: $user->id);
            throw ValidationException::withMessages(['email' => ['This account is not active.']]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($deviceName, ['*'], now()->addMinutes((int) config('sanctum.expiration', 720)));
        $this->audit->record('auth.login', $user, userId: $user->id);

        return ['accessToken' => $token->plainTextToken, 'tokenType' => 'Bearer', 'expiresAt' => $token->accessToken->expires_at?->toIso8601String(), 'user' => $user->fresh('permissions')];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
        $this->audit->record('auth.logout', $user, userId: $user->id);
    }

    public function changePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->getKey())->delete();
        $this->audit->record('auth.password_changed', $user, userId: $user->id);
    }

    public function sendResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);
        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }
        return trans($status);
    }

    public function resetPassword(array $credentials): string
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
            $this->audit->record('auth.password_reset', $user, userId: $user->id);
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }
        return trans($status);
    }
}

