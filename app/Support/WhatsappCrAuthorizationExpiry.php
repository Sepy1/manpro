<?php

namespace App\Support;

use App\Models\WhatsappCrAuthorizationDispatch;

final class WhatsappCrAuthorizationExpiry
{
    public const TTL_HOURS = 24;

    public const TTL_MINUTES = self::TTL_HOURS * 60;

    public static function ttlLabel(): string
    {
        return '1 hari';
    }

    public static function isExpired(WhatsappCrAuthorizationDispatch $dispatch): bool
    {
        if (self::hasDecision($dispatch)) {
            return true;
        }

        $created = $dispatch->created_at;
        if ($created === null) {
            return false;
        }

        return $created->copy()->addHours(self::TTL_HOURS)->isPast();
    }

    public static function hasDecision(WhatsappCrAuthorizationDispatch $dispatch): bool
    {
        $dispatch->loadMissing('externCr');
        $cr = $dispatch->externCr;

        return $cr !== null && $cr->hasWaAuthorizationDecision();
    }

    /** @return 'decided'|'timeout'|null */
    public static function expiredReason(WhatsappCrAuthorizationDispatch $dispatch): ?string
    {
        if (! self::isExpired($dispatch)) {
            return null;
        }

        if (self::hasDecision($dispatch)) {
            return 'decided';
        }

        return 'timeout';
    }
}
