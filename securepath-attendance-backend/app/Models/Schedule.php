<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'type', 'start_time', 'end_time', 'grace_period_minutes', 'break_duration_minutes', 'break_window', 'effective_from', 'effective_until', 'status'];

    protected function casts(): array { return ['effective_from' => 'date', 'effective_until' => 'date']; }
    public function days(): HasMany { return $this->hasMany(ScheduleDay::class); }
    public function people(): BelongsToMany { return $this->belongsToMany(Person::class, 'person_schedule')->withPivot(['effective_from', 'effective_until'])->withTimestamps(); }
    public function departments(): BelongsToMany { return $this->belongsToMany(Department::class, 'department_schedule')->withTimestamps(); }
    public function getRouteKeyName(): string { return 'code'; }
}
