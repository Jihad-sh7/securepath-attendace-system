<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricEnrollmentRequest extends Model
{
    protected $fillable = ['person_id', 'requested_by', 'token_hash', 'status', 'expires_at', 'completed_at', 'failure_reason'];
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['status' => EnrollmentStatus::class, 'expires_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}

