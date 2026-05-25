<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ExternCrVendorAccess
{
    public static function ensureVendorUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->role === 'vendor', 403);

        return $user;
    }

    public static function scopeAssignedTo(Builder $query, User $vendorUser): Builder
    {
        return $query->where('vendor_pic_user_id', $vendorUser->id);
    }

    public static function authorizeAssignedCr(ExternCr $externCr, ?User $vendorUser = null): void
    {
        $vendorUser ??= self::ensureVendorUser();

        abort_unless((int) $externCr->vendor_pic_user_id === (int) $vendorUser->id, 403);
    }
}
