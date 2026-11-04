<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class MobileDevice extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'identifier', 'secret', 'status', 'last_seen_at', 'metadata'];
    protected $hidden = ['secret'];
    protected function casts(): array { return ['secret' => 'hashed', 'status' => AccountStatus::class, 'last_seen_at' => 'datetime', 'metadata' => 'array']; }
}

