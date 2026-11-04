<?php

namespace App\Models;

use App\Enums\AttendanceActionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    protected $fillable = ['attendance_id', 'person_id', 'type', 'occurred_at', 'source', 'performed_by_user_id', 'note', 'metadata'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'source' => AttendanceActionSource::class, 'metadata' => 'array'];
    }

    public function attendance(): BelongsTo { return $this->belongsTo(AttendanceRecord::class); }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by_user_id'); }
}

