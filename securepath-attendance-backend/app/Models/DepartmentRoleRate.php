<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentRoleRate extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'role_id', 'hourly_rate', 'currency', 'effective_from', 'effective_until', 'created_by'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:4', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
