<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'department_id', 'description'];
    protected function casts(): array { return ['date' => 'date']; }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}

