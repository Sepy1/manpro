<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccessLog extends Model
{
    protected $fillable = [
        'vendor', 'device_name', 'device_id', 'virtual_domain', 'event_type',
        'event_category', 'username', 'auth_server', 'source_ip', 'destination_ip',
        'tunnel_ip', 'source_interface', 'destination_interface', 'method', 'action',
        'status', 'original_status', 'reason', 'profile', 'event_date', 'event_clock',
        'timezone', 'event_time', 'log_id', 'log_description', 'severity', 'message',
        'raw_log',
    ];

    protected function casts(): array
    {
        return ['event_date' => 'date:Y-m-d'];
    }
}
