<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAttackLog extends Model
{
    protected $fillable = [
        'device',
        'severity',
        'attack',
        'attackid',
        'subtype',
        'action',
        'srcip',
        'srcport',
        'dstip',
        'dstport',
        'service',
        'method',
        'url',
        'event_time',
    ];

    protected function casts(): array
    {
        return [
            'srcport' => 'integer',
            'dstport' => 'integer',
        ];
    }
}
