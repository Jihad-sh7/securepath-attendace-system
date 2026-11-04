<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_public', 'updated_by'];
    protected $hidden = ['value'];
    protected function casts(): array { return ['is_public' => 'boolean']; }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}

