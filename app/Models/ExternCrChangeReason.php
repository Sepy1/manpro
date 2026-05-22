<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternCrChangeReason extends Model
{
    protected $table = 'extern_cr_change_reasons';

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
