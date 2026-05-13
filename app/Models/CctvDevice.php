<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CctvDevice extends Model
{
    protected $fillable = [
        'branch',
        'office',
        'dvr_brand',
        'channel_count',
        'harddisk',
        'monitor',
        'connection_status',
        'device_status',
        'notes',
        'dvr_photo_path',
        'monitor_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'channel_count' => 'integer',
        ];
    }
}
