<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnClient extends Model
{
    protected $fillable = ['name', 'username', 'common_name', 'department', 'branch_office', 'vpn_group_id', 'inventory_number', 'vpn_ip', 'allowed_destinations', 'certificate_issued_at', 'certificate_expires_at', 'status', 'certificate_status', 'last_connected_at', 'created_by'];

    protected function casts(): array
    {
        return ['allowed_destinations' => 'array', 'certificate_issued_at' => 'date', 'certificate_expires_at' => 'date', 'last_connected_at' => 'datetime'];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(VpnConnection::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(VpnAdminActivity::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VpnGroup::class, 'vpn_group_id');
    }

    public function effectiveAllowedDestinations(): array
    {
        if (! empty($this->allowed_destinations)) {
            return $this->allowed_destinations;
        }

        return $this->group?->allowed_destinations ?: [];
    }
}
