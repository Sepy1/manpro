<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGIN_FAILED = 'login_failed';
    public const TYPE_MENU_OPEN = 'menu_open';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'activity_type',
        'attempted_email',
        'failure_reason',
        'route_name',
        'menu_name',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
