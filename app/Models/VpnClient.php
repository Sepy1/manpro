<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class VpnClient extends Model
{
    protected $fillable = ['name', 'username', 'common_name', 'department', 'branch_office', 'vpn_group_id', 'inventory_number', 'vpn_ip', 'allowed_destinations', 'access_schedule', 'certificate_issued_at', 'certificate_expires_at', 'status', 'certificate_status', 'last_connected_at', 'mfa_enabled_at', 'mfa_last_reset_at', 'created_by'];

    protected function casts(): array
    {
        return ['allowed_destinations' => 'array', 'access_schedule' => 'array', 'certificate_issued_at' => 'date', 'certificate_expires_at' => 'date', 'last_connected_at' => 'datetime', 'mfa_enabled_at' => 'datetime', 'mfa_last_reset_at' => 'datetime'];
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

    public function isWithinAccessSchedule(?Carbon $at = null): bool
    {
        $schedule = $this->effectiveAccessSchedule();
        if (empty($schedule)) {
            return true;
        }

        $timezone = app()->bound('config') ? config('app.timezone', 'Asia/Jakarta') : 'Asia/Jakarta';
        $at = ($at ?? now())->copy()->timezone($timezone);
        $days = array_map('intval', $schedule['days'] ?? []);
        $start = (string) ($schedule['start'] ?? '');
        $end = (string) ($schedule['end'] ?? '');
        if ($days === [] || ! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            return false;
        }

        $time = $at->format('H:i');
        $day = $at->dayOfWeekIso;
        if ($start < $end) {
            return in_array($day, $days, true) && $time >= $start && $time < $end;
        }

        $previousDay = $day === 1 ? 7 : $day - 1;

        return (in_array($day, $days, true) && $time >= $start)
            || (in_array($previousDay, $days, true) && $time < $end);
    }

    public function effectiveAccessSchedule(): ?array
    {
        if (! empty($this->access_schedule)) {
            return $this->access_schedule;
        }

        if (! $this->exists && ! $this->relationLoaded('group')) {
            return null;
        }

        return $this->group?->access_schedule ?: null;
    }
}
