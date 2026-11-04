<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'status', 'title', 'avatar_path', 'two_factor_enabled', 'person_id', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->permissions()->where('name', $permission)->exists();
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if (! $field && is_string($value) && preg_match('/^(?:USR|ADM)-(\d+)$/', $value, $matches)) $value = (int) $matches[1];
        return parent::resolveRouteBinding($value, $field);
    }
}
