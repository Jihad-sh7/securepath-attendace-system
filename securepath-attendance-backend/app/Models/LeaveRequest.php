<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = ['request_code', 'person_id', 'start_date', 'end_date', 'type', 'reason', 'status', 'requested_at', 'reviewed_by', 'reviewed_at', 'review_note'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'requested_at' => 'datetime', 'reviewed_at' => 'datetime', 'status' => LeaveStatus::class]; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function getRouteKeyName(): string { return 'request_code'; }
}
