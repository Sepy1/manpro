<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class NetworkMonitorSetting extends Model
{
    public const DEFAULT_INTERVAL_MINUTES = 5;
    public const MIN_INTERVAL_MINUTES = 1;
    public const MAX_INTERVAL_MINUTES = 1440;

    protected $fillable = ['polling_interval_minutes'];

    protected function casts(): array
    {
        return ['polling_interval_minutes' => 'integer'];
    }

    public static function intervalMinutes(): int
    {
        try {
            $minutes = (int) (self::query()->value('polling_interval_minutes') ?? self::DEFAULT_INTERVAL_MINUTES);
        } catch (Throwable) {
            $minutes = self::DEFAULT_INTERVAL_MINUTES;
        }

        return max(self::MIN_INTERVAL_MINUTES, min(self::MAX_INTERVAL_MINUTES, $minutes));
    }
}
