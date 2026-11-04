<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    protected $fillable = ['attendance_id', 'requested_by', 'previous_values', 'requested_values', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];
    protected function casts(): array { return ['previous_values' => 'array', 'requested_values' => 'array', 'status' => CorrectionStatus::class, 'reviewed_at' => 'datetime']; }
    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}

