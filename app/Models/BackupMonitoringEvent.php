<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupMonitoringEvent extends Model
{
    protected $fillable = [
        'tanggal',
        'nama_db',
        'ukuran',
        'status',
        'lokasi_file',
        'server',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'ukuran' => 'decimal:2',
        ];
    }
}
