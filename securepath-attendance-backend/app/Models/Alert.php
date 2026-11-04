<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = ['alert_code', 'type', 'severity', 'title', 'message', 'person_id', 'attendance_id', 'recognition_event_id', 'status', 'assigned_to', 'resolved_by', 'resolved_at', 'metadata'];
    protected function casts(): array { return ['severity' => AlertSeverity::class, 'status' => AlertStatus::class, 'resolved_at' => 'datetime', 'metadata' => 'array']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function recognitionEvent(): BelongsTo { return $this->belongsTo(RecognitionEvent::class); }
    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function getRouteKeyName(): string { return 'alert_code'; }
}
