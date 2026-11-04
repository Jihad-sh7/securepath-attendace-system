<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDay extends Model
{
    public $timestamps = false;
    protected $fillable = ['schedule_id', 'day_of_week', 'is_working_day', 'start_time', 'end_time'];
    protected function casts(): array { return ['is_working_day' => 'boolean']; }
    public function schedule(): BelongsTo { return $this->belongsTo(Schedule::class); }
}

