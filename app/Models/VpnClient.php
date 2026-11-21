<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnClient extends Model
{
    protected $fillable = ['name', 'username', 'common_name', 'department', 'branch_office', 'inventory_number', 'vpn_ip', 'certificate_issued_at', 'certificate_expires_at', 'status', 'certificate_status', 'last_connected_at', 'created_by'];

    protected function casts(): array
    {
        return ['certificate_issued_at' => 'date', 'certificate_expires_at' => 'date', 'last_connected_at' => 'datetime'];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(VpnConnection::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(VpnAdminActivity::class);
    }
}
