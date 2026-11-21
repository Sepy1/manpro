<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnConnection extends Model
{
    protected $fillable = ['vpn_client_id', 'common_name', 'real_ip', 'vpn_ip', 'connected_at', 'disconnected_at', 'bytes_received', 'bytes_sent', 'disconnect_reason'];

    protected function casts(): array
    {
        return ['connected_at' => 'datetime', 'disconnected_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(VpnClient::class, 'vpn_client_id');
    }
}
