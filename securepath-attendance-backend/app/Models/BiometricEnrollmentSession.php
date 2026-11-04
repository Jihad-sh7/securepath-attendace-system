<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricEnrollmentSession extends Model
{
    protected $fillable = ['uuid', 'person_id', 'requested_by', 'status', 'is_reenrollment', 'model_version', 'quality_score', 'failure_reason', 'completed_at'];
    protected $hidden = ['failure_reason'];
    protected function casts(): array { return ['status' => EnrollmentStatus::class, 'is_reenrollment' => 'boolean', 'quality_score' => 'decimal:4', 'completed_at' => 'datetime']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function samples(): HasMany { return $this->hasMany(FaceSample::class, 'biometric_enrollment_session_id'); }
}
