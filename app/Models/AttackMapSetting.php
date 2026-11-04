<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class AttackMapSetting extends Model
{
    public const DEFAULT_LOCATION = [
        'country' => 'Indonesia',
        'country_code' => 'ID',
        'city' => 'Semarang',
        'latitude' => -6.966667,
        'longitude' => 110.416664,
        'is_local' => true,
    ];

    protected $fillable = ['headquarters_kantor_id', 'central_ip_ranges'];

    protected function casts(): array
    {
        return [
            'headquarters_kantor_id' => 'integer',
            'central_ip_ranges' => 'array',
        ];
    }

    public function headquarters(): BelongsTo
    {
        return $this->belongsTo(Kantor::class, 'headquarters_kantor_id');
    }

    public static function configuration(): array
    {
        try {
            $setting = self::query()->with('headquarters')->first();
            $office = $setting?->headquarters;
            $location = $office && $office->latitude !== null && $office->longitude !== null
                ? [
                    'country' => 'Indonesia',
                    'country_code' => 'ID',
                    'city' => $office->nama_kantor,
                    'latitude' => (float) $office->latitude,
                    'longitude' => (float) $office->longitude,
                    'is_local' => true,
                ]
                : self::DEFAULT_LOCATION;

            return [
                'central_ip_ranges' => array_values(array_filter($setting?->central_ip_ranges ?? [])),
                'location' => $location,
            ];
        } catch (Throwable) {
            return ['central_ip_ranges' => [], 'location' => self::DEFAULT_LOCATION];
        }
    }
}
