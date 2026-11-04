<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = ['year', 'month', 'starts_on', 'ends_on', 'status', 'currency', 'calculated_at', 'generated_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'calculated_at' => 'datetime'];
    }

    public function entries(): HasMany { return $this->hasMany(PayrollEntry::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
}
