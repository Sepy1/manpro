<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const CATEGORIES = [
        'Development Aplikasi',
        'Change Request',
        'Audit',
        'Infrastruktur',
        'Pengadaan',
        'Kerjasama Vendor',
    ];

    protected $fillable = [
        'user_id',
        'vendor_id',
        'name',
        'division',
        'category',
        'description',
        'follow_up',
        'url',
        'pic',
        'pic_user_id',
        'deadline',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('sort_order');
    }
}
