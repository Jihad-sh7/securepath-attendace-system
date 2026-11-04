<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'manager_id', 'status', 'max_overtime_minutes', 'auto_checkout_enabled'];

    protected function casts(): array { return ['auto_checkout_enabled' => 'boolean']; }

    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function people(): HasMany { return $this->hasMany(Person::class); }
    public function schedules(): BelongsToMany { return $this->belongsToMany(Schedule::class, 'department_schedule')->withTimestamps(); }
    public function rates(): HasMany { return $this->hasMany(DepartmentRoleRate::class); }
    public function getRouteKeyName(): string { return 'code'; }
}
