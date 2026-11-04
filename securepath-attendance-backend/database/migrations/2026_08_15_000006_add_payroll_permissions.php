<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payroll.view', 'payroll.manage', 'payroll.export'] as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['group' => 'payroll', 'description' => str_replace('.', ' ', ucfirst($name)), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['payRates.view', 'payRates.manage'] as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['group' => 'payRates', 'description' => str_replace('.', ' ', ucfirst($name)), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['payroll.view', 'payroll.manage', 'payroll.export', 'payRates.view', 'payRates.manage'])->delete();
    }
};
