<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

trait CreatesAuthorizedUsers
{
    protected function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'email' => 'super@test.local']);
    }

    protected function adminWith(array $permissions): User
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        return $admin->load('permissions');
    }
}

