<?php

namespace App\Models;

use App\Enums\AttendanceActionSource;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LivenessResult;
use App\Enums\VerificationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['event_code', 'person_id', 'attendance_date', 'scheduled_start', 'scheduled_end', 'check_in_at', 'check_in_source', 'checked_in_by_user_id', 'check_out_at', 'check_out_source', 'checked_out_by_user_id', 'auto_checkout_reason', 'status', 'minutes_late', 'worked_minutes', 'verification_method', 'recognition_confidence', 'liveness_result', 'source', 'source_identifier', 'location', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'check_in_source' => AttendanceActionSource::class, 'check_out_source' => AttendanceActionSource::class, 'status' => AttendanceStatus::class, 'verification_method' => VerificationMethod::class, 'source' => AttendanceSource::class, 'liveness_result' => LivenessResult::class, 'recognition_confidence' => 'decimal:4'];
    }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function corrections(): HasMany { return $this->hasMany(AttendanceCorrection::class, 'attendance_id'); }
    public function events(): HasMany { return $this->hasMany(AttendanceEvent::class, 'attendance_id')->orderBy('occurred_at'); }
    public function recognitionEvents(): HasMany { return $this->hasMany(RecognitionEvent::class, 'attendance_id'); }
    public function alerts(): HasMany { return $this->hasMany(Alert::class, 'attendance_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function checkedInBy(): BelongsTo { return $this->belongsTo(User::class, 'checked_in_by_user_id'); }
    public function checkedOutBy(): BelongsTo { return $this->belongsTo(User::class, 'checked_out_by_user_id'); }
    public function getRouteKeyName(): string { return 'event_code'; }
}
