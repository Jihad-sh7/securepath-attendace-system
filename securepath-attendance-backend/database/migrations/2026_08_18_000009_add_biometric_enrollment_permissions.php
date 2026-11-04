<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = ['biometrics.enroll', 'biometrics.reenroll', 'biometrics.delete'];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['group' => 'biometrics', 'description' => str_replace(['.', '_'], ' ', ucfirst($name)), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', $this->permissions)->delete();
    }
};
