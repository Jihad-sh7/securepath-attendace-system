<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('department_id')->constrained('roles')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_overtime_minutes')->nullable()->after('status');
            $table->boolean('auto_checkout_enabled')->default(false)->after('max_overtime_minutes')->index();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('checkout_source', 32)->nullable()->after('check_out_at')->index();
            $table->string('auto_checkout_reason')->nullable()->after('checkout_source');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->foreignId('attendance_id')->nullable()->after('person_id')->constrained('attendance_records')->nullOnDelete();
            $table->index(['type', 'attendance_id']);
        });

        Schema::create('department_role_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->decimal('hourly_rate', 12, 4);
            $table->char('currency', 3)->default('USD');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['department_id', 'role_id', 'effective_from'], 'rates_lookup_index');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->default('calculated')->index();
            $table->char('currency', 3)->default('USD');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('person_code');
            $table->string('person_name');
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->string('role_code')->nullable();
            $table->string('role_name')->nullable();
            $table->unsignedInteger('regular_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('total_worked_minutes')->default(0);
            $table->decimal('hourly_rate', 12, 4)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('regular_pay', 14, 2)->nullable();
            $table->decimal('overtime_pay', 14, 2)->nullable();
            $table->decimal('total_pay', 14, 2)->nullable();
            $table->string('status', 32)->default('calculated')->index();
            $table->json('issues')->nullable();
            $table->json('attendance_breakdown')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'person_id']);
            $table->index(['payroll_period_id', 'department_id', 'role_id'], 'payroll_filter_index');
        });

        $codes = [];
        foreach (DB::table('people')->whereNotNull('job_title')->where('job_title', '<>', '')->select('job_title')->distinct()->pluck('job_title') as $title) {
            $base = Str::upper(Str::slug((string) $title, '_')) ?: 'ROLE';
            $base = Str::limit($base, 52, '');
            $code = $base;
            $suffix = 2;
            while (isset($codes[$code]) || DB::table('roles')->where('code', $code)->exists()) {
                $code = $base.'_'.$suffix++;
            }
            $codes[$code] = true;
            $roleId = DB::table('roles')->insertGetId([
                'name' => $title,
                'code' => $code,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('people')->where('job_title', $title)->update(['role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('department_role_rates');
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['type', 'attendance_id']);
            $table->dropConstrainedForeignId('attendance_id');
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['checkout_source', 'auto_checkout_reason']);
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['max_overtime_minutes', 'auto_checkout_enabled']);
        });
        Schema::table('people', fn (Blueprint $table) => $table->dropConstrainedForeignId('role_id'));
        Schema::dropIfExists('roles');
    }
};
