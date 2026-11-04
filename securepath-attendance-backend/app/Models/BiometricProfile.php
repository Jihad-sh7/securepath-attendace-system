<?php

namespace App\Models;

use App\Enums\BiometricStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricProfile extends Model
{
    protected $fillable = ['person_id', 'status', 'enrolled_at', 'revoked_at', 'last_updated_at', 'consent_status', 'consent_at', 'model_version', 'secure_reference', 'embedding', 'embedding_dimension', 'quality_score', 'metadata'];
    protected $hidden = ['secure_reference', 'embedding'];
    protected function casts(): array { return ['status' => BiometricStatus::class, 'enrolled_at' => 'datetime', 'revoked_at' => 'datetime', 'last_updated_at' => 'datetime', 'consent_at' => 'datetime', 'secure_reference' => 'encrypted', 'embedding' => 'encrypted:array', 'quality_score' => 'decimal:4', 'metadata' => 'encrypted:array']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function samples(): HasMany { return $this->hasMany(FaceSample::class); }
}
