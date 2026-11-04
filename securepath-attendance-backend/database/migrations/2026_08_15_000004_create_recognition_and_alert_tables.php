<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('not_registered')->index();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->string('consent_status', 32)->default('pending');
            $table->timestamp('consent_at')->nullable();
            $table->string('model_version')->nullable();
            $table->text('secure_reference')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('biometric_enrollment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->string('secret');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recognition_events', function (Blueprint $table) {
            $table->id();
            $table->string('recognition_code')->unique();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->decimal('confidence', 6, 4)->nullable();
            $table->string('liveness_result', 32)->index();
            $table->string('recognition_status', 32)->index();
            $table->string('verification_status', 32)->index();
            $table->string('source_device_identifier')->index();
            $table->string('location')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['person_id', 'occurred_at']);
            $table->index(['recognition_status', 'verification_status']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code')->unique();
            $table->string('type', 64)->index();
            $table->string('severity', 32)->index();
            $table->string('title');
            $table->text('message');
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recognition_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('recognition_events');
        Schema::dropIfExists('mobile_devices');
        Schema::dropIfExists('biometric_enrollment_requests');
        Schema::dropIfExists('biometric_profiles');
    }
};
