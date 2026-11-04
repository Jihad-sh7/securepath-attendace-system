<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceSample extends Model
{
    protected $fillable = ['person_id', 'biometric_profile_id', 'biometric_enrollment_session_id', 'pose', 'image_path', 'quality_score'];
    protected $hidden = ['image_path'];
    protected function casts(): array { return ['image_path' => 'encrypted', 'quality_score' => 'decimal:4']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function biometricProfile(): BelongsTo { return $this->belongsTo(BiometricProfile::class); }
    public function enrollmentSession(): BelongsTo { return $this->belongsTo(BiometricEnrollmentSession::class, 'biometric_enrollment_session_id'); }
}
