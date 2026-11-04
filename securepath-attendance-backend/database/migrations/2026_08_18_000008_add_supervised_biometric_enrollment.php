<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_profiles', function (Blueprint $table) {
            $table->longText('embedding')->nullable()->after('secure_reference');
            $table->unsignedInteger('embedding_dimension')->nullable()->after('embedding');
            $table->decimal('quality_score', 6, 4)->nullable()->after('embedding_dimension');
            $table->timestamp('revoked_at')->nullable()->after('enrolled_at');
        });

        Schema::create('biometric_enrollment_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->boolean('is_reenrollment')->default(false);
            $table->string('model_version')->nullable();
            $table->decimal('quality_score', 6, 4)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['person_id', 'created_at']);
        });

        Schema::create('face_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('biometric_enrollment_session_id')->constrained()->cascadeOnDelete();
            $table->string('pose', 16);
            $table->text('image_path');
            $table->decimal('quality_score', 6, 4)->nullable();
            $table->timestamps();
            $table->unique(['biometric_enrollment_session_id', 'pose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_samples');
        Schema::dropIfExists('biometric_enrollment_sessions');
        Schema::table('biometric_profiles', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_dimension', 'quality_score', 'revoked_at']);
        });
    }
};
