<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiConflictException;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\AdminAccountCreatedNotification;
use App\Notifications\AdminPasswordResetNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $admin = User::create([
                'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'password' => $data['password'],
                'role' => UserRole::Admin, 'status' => $data['status'] ?? AccountStatus::Active->value, 'title' => 'Admin',
            ]);
            $this->syncPermissions($admin, $data['permissions']);
            $admin->notify(new AdminAccountCreatedNotification($data['password']));
            $this->audit->record('admin.created', $admin, newValues: ['email' => $admin->email, 'permissions' => $data['permissions']]);
            return $admin->load('permissions');
        });
    }

    public function update(User $admin, array $data): User
    {
        $this->guardMutable($admin);
        $old = $admin->only(['name', 'email', 'phone', 'status']);
        $admin->update($data);
        $this->audit->record('admin.updated', $admin, $old, $admin->only(array_keys($old)));
        return $admin->fresh('permissions');
    }

    public function updateStatus(User $admin, string $status): User
    {
        $this->guardMutable($admin);
        return $this->update($admin, ['status' => $status]);
    }

    public function permissions(User $admin, array $permissions): User
    {
        $this->guardMutable($admin);
        return DB::transaction(function () use ($admin, $permissions): User {
            $old = $admin->permissions()->pluck('name')->all();
            $this->syncPermissions($admin, $permissions);
            $this->audit->record('admin.permissions_updated', $admin, ['permissions' => $old], ['permissions' => $permissions]);
            return $admin->fresh('permissions');
        });
    }

    public function resetPassword(User $admin): string
    {
        $this->guardMutable($admin);
        $password = 'Tmp!'.Str::password(14, symbols: false);
        $admin->update(['password' => $password]);
        $admin->tokens()->delete();
        $admin->notify(new AdminPasswordResetNotification($password));
        $this->audit->record('admin.password_reset', $admin);
        return $password;
    }

    public function delete(User $admin): void
    {
        $this->guardMutable($admin);
        DB::transaction(function () use ($admin): void {
            $admin->tokens()->delete();
            $admin->permissions()->detach();
            $this->audit->record('admin.deleted', $admin, $admin->only(['name', 'email', 'status']));
            $admin->delete();
        });
    }

    private function syncPermissions(User $admin, array $permissions): void
    {
        $ids = Permission::whereIn('name', $permissions)->pluck('id');
        if ($ids->count() !== count(array_unique($permissions))) {
            throw new ApiConflictException('One or more permissions are not available.', 'INVALID_PERMISSIONS');
        }
        $admin->permissions()->sync($ids);
    }

    private function guardMutable(User $admin): void
    {
        if ($admin->role !== UserRole::Admin) {
            throw new ApiConflictException('The Super Admin account cannot be changed.', 'PROTECTED_SUPER_ADMIN');
        }
    }
}

