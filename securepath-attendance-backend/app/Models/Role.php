<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'status'];

    public function people(): HasMany { return $this->hasMany(Person::class); }
    public function rates(): HasMany { return $this->hasMany(DepartmentRoleRate::class); }
    public function getRouteKeyName(): string { return 'code'; }
}
