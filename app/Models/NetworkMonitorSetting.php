<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class NetworkMonitorSetting extends Model
{
    public const DEFAULT_INTERVAL_MINUTES = 5;
    public const MIN_INTERVAL_MINUTES = 1;
    public const MAX_INTERVAL_MINUTES = 1440;
    public const DEFAULT_HIGH_PING_THRESHOLD_MS = 50;
    public const MIN_HIGH_PING_THRESHOLD_MS = 1;
    public const MAX_HIGH_PING_THRESHOLD_MS = 5000;

    protected $fillable = ['polling_interval_minutes', 'high_ping_threshold_ms'];

    protected function casts(): array
    {
        return [
            'polling_interval_minutes' => 'integer',
            'high_ping_threshold_ms' => 'integer',
        ];
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

    public static function highPingThresholdMs(): int
    {
        try {
            $threshold = (int) (self::query()->value('high_ping_threshold_ms') ?? self::DEFAULT_HIGH_PING_THRESHOLD_MS);
        } catch (Throwable) {
            $threshold = self::DEFAULT_HIGH_PING_THRESHOLD_MS;
        }

        return max(self::MIN_HIGH_PING_THRESHOLD_MS, min(self::MAX_HIGH_PING_THRESHOLD_MS, $threshold));
    }
}
