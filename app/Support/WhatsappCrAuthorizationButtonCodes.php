<?php

namespace App\Support;

use App\Models\ExternCr;

/**
 * Prefiks pada field {@code interactive.button_reply.id} (payload tombol template).
 *
 * Label tombol di Meta bisa "Setuju" / "Tidak" (atau setara); API hanya mengirim string payload di bawah.
 * Format baru: {@see self::PREFIX_APPROVE} / {@see self::PREFIX_REJECT} + token unik per kiriman.
 * Format lama {@code APR_} / {@code REJ_} tetap diparse untuk kompatibilitas mundur.
 */
final class WhatsappCrAuthorizationButtonCodes
{
    public const PREFIX_APPROVE = 'APPROVE_CR_';

    public const PREFIX_REJECT = 'REJECT_CR_';

    /** @deprecated Hanya untuk webhook pesan lama; pengiriman baru memakai {@see self::PREFIX_APPROVE} */
    private const LEGACY_PREFIX_APPROVE = 'APR_';

    /** @deprecated Hanya untuk webhook pesan lama; pengiriman baru memakai {@see self::PREFIX_REJECT} */
    private const LEGACY_PREFIX_REJECT = 'REJ_';

    public static function approvePayload(string $interactionToken): string
    {
        return self::PREFIX_APPROVE.$interactionToken;
    }

    public static function rejectPayload(string $interactionToken): string
    {
        return self::PREFIX_REJECT.$interactionToken;
    }

    /**
     * @return array{interaction_token: string, decision: string}|null
     */
    public static function tokenDecisionFromPayloadId(string $buttonId): ?array
    {
        $id = trim($buttonId);
        if ($id === '') {
            return null;
        }

        $pairs = [
            [self::PREFIX_APPROVE, ExternCr::WA_AUTH_APPROVED],
            [self::LEGACY_PREFIX_APPROVE, ExternCr::WA_AUTH_APPROVED],
            [self::PREFIX_REJECT, ExternCr::WA_AUTH_REJECTED],
            [self::LEGACY_PREFIX_REJECT, ExternCr::WA_AUTH_REJECTED],
        ];

        foreach ($pairs as [$prefix, $decision]) {
            if (str_starts_with($id, $prefix)) {
                $token = substr($id, strlen($prefix));

                return $token !== '' ? ['interaction_token' => $token, 'decision' => $decision] : null;
            }
        }

        return null;
    }
}
