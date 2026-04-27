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
        'name',
        'category',
        'description',
        'url',
        'pic',
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

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('sort_order');
    }
}
