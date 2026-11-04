<?php

namespace App\Models;

use App\Enums\LivenessResult;
use App\Enums\RecognitionStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecognitionEvent extends Model
{
    use HasFactory;

    protected $fillable = ['recognition_code', 'event_uuid', 'person_id', 'attendance_id', 'confidence', 'liveness_result', 'recognition_status', 'verification_status', 'source_device_identifier', 'location', 'occurred_at', 'metadata', 'verified_by', 'verified_at'];
    protected function casts(): array { return ['confidence' => 'decimal:4', 'liveness_result' => LivenessResult::class, 'recognition_status' => RecognitionStatus::class, 'verification_status' => VerificationStatus::class, 'occurred_at' => 'datetime', 'metadata' => 'array', 'verified_at' => 'datetime']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function alerts(): HasMany { return $this->hasMany(Alert::class); }
    public function getRouteKeyName(): string { return 'recognition_code'; }
}
