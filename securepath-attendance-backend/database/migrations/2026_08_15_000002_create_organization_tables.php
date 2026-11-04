<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('person_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->index();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default('employee')->index();
            $table->string('job_title')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->string('avatar_path')->nullable();
            $table->boolean('face_registered')->default(false)->index();
            $table->boolean('biometric_consent')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 32)->default('fixed');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_period_minutes')->default(10);
            $table->unsignedSmallInteger('break_duration_minutes')->default(0);
            $table->string('break_window')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unique(['schedule_id', 'day_of_week']);
        });

        Schema::create('person_schedule', function (Blueprint $table) {
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->primary(['person_id', 'schedule_id']);
        });

        Schema::create('department_schedule', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['department_id', 'schedule_id']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['date', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('department_schedule');
        Schema::dropIfExists('person_schedule');
        Schema::dropIfExists('schedule_days');
        Schema::dropIfExists('schedules');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('person_id'));
        Schema::dropIfExists('people');
        Schema::dropIfExists('departments');
    }
};
