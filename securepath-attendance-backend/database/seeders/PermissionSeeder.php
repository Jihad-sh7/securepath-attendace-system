<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::GROUPS as $group => $permissions) {
            foreach ($permissions as $permission) Permission::updateOrCreate(['name' => $permission], ['group' => $group, 'description' => str_replace(['.', '_'], [' ', ' '], ucfirst($permission))]);
        }
    }
}

