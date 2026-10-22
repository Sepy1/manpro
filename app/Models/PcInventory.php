<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcInventory extends Model
{
    protected $fillable = [
        'hostname',
        'machine_guid',
        'serial_number',
        'mainboard_type',
        'antivirus_status',
        'antivirus_name',
        'os_info',
        'cpu_name',
        'cpu_cores',
        'cpu_threads',
        'ram_gb',
        'disk_total_gb',
        'disk_free_gb',
        'disk_detail',
        'mac_address',
        'ip_address',
        'kode_kantor',
        'kantor_id',
        'kas_kantor_id',
        'kode_kas',
        'nama_user',
        'jabatan',
        'status_perangkat',
        'last_seen_at',
        'sender_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'cpu_cores' => 'integer',
            'cpu_threads' => 'integer',
            'ram_gb' => 'decimal:1',
            'disk_total_gb' => 'decimal:1',
            'disk_free_gb' => 'decimal:1',
            'last_seen_at' => 'datetime',
        ];
    }

    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    public function kasKantor(): BelongsTo
    {
        return $this->belongsTo(KasKantor::class, 'kas_kantor_id');
    }
}
