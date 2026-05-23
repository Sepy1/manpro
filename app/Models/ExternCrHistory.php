<?php

namespace App\Models;

use App\Enums\ExternCrHistoryEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternCrHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'extern_cr_id',
        'user_id',
        'event',
        'summary',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'event' => ExternCrHistoryEvent::class,
            'properties' => 'array',
        ];
    }

    public function externCr(): BelongsTo
    {
        return $this->belongsTo(ExternCr::class, 'extern_cr_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
