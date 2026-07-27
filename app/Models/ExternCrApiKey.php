<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternCrApiKey extends Model
{
    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
