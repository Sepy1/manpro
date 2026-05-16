<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DcDrcDevice extends Model
{
    protected $fillable = [
        'server_name',
        'device_type',
        'host_server',
        'vm_host_id',
        'ip_address',
        'vlan',
        'nic_model',
        'os',
        'cpu_cores',
        'ram_gb',
        'storage_gb',
        'site',
        'system_role',
        'environment',
        'owner_team',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'vm_host_id' => 'integer',
            'cpu_cores' => 'integer',
            'ram_gb' => 'integer',
            'storage_gb' => 'integer',
        ];
    }

    public function vmHost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'vm_host_id');
    }

    public function hostedVms(): HasMany
    {
        return $this->hasMany(self::class, 'vm_host_id');
    }
}
