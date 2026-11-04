<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\BiometricStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['person_code', 'first_name', 'last_name', 'full_name', 'email', 'phone', 'department_id', 'role_id', 'type', 'job_title', 'status', 'avatar_path', 'face_registered', 'biometric_consent'];

    protected function casts(): array
    {
        return ['status' => AccountStatus::class, 'face_registered' => 'boolean', 'biometric_consent' => 'boolean'];
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function schedules(): BelongsToMany { return $this->belongsToMany(Schedule::class, 'person_schedule')->withPivot(['effective_from', 'effective_until'])->withTimestamps(); }
    public function attendanceRecords(): HasMany { return $this->hasMany(AttendanceRecord::class); }
    public function openAttendance(): HasOne { return $this->hasOne(AttendanceRecord::class)->whereNotNull('check_in_at')->whereNull('check_out_at')->latestOfMany('check_in_at'); }
    public function recognitionEvents(): HasMany { return $this->hasMany(RecognitionEvent::class); }
    public function biometricProfile(): HasOne { return $this->hasOne(BiometricProfile::class); }
    public function enrollmentRequests(): HasMany { return $this->hasMany(BiometricEnrollmentRequest::class); }
    public function biometricEnrollmentSessions(): HasMany { return $this->hasMany(BiometricEnrollmentSession::class); }
    public function faceSamples(): HasMany { return $this->hasMany(FaceSample::class); }
    public function leaveRequests(): HasMany { return $this->hasMany(LeaveRequest::class); }
    public function payrollEntries(): HasMany { return $this->hasMany(PayrollEntry::class); }
    public function user(): HasOne { return $this->hasOne(User::class); }
    public function getRouteKeyName(): string { return 'person_code'; }

    public function isBiometricReady(): bool
    {
        $profile = $this->relationLoaded('biometricProfile') ? $this->biometricProfile : $this->biometricProfile()->first();
        return $this->status === AccountStatus::Active && $this->face_registered && $profile
            && in_array($profile->status, [BiometricStatus::Registered, BiometricStatus::Enrolled], true);
    }
}
