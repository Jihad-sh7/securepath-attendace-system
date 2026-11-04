<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date')->index();
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->timestamp('check_in_at')->nullable()->index();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedInteger('minutes_late')->default(0);
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->string('verification_method', 32)->index();
            $table->decimal('recognition_confidence', 6, 4)->nullable();
            $table->string('liveness_result', 32)->nullable();
            $table->string('source', 32)->index();
            $table->string('source_identifier')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['person_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('previous_values');
            $table->json('requested_values');
            $table->text('reason');
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('type', 64);
            $table->text('reason');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['person_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
    }
};
