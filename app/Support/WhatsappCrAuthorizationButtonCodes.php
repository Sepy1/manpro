<?php

namespace App\Support;

use App\Models\ExternCr;
use Illuminate\Support\Facades\URL;

/**
 * Prefiks payload quick reply (legacy) dan URL tombol CTA template Meta.
 *
 * Template Meta `change_request_manpro` (mode URL):
 * - body {{1}}–{{4}}
 * - tombol Setujui & Tolak: URL template = `{{1}}` (seluruh URL dinamis dari Laravel)
 * - Setujui → `https://manpro.bkkjateng.co.id/{token32}`
 * - Tolak → `https://manpro.bkkjateng.co.id/reject-{token32}`
 */
final class WhatsappCrAuthorizationButtonCodes
{
    public const PREFIX_APPROVE = 'APPROVE_CR_';

    public const PREFIX_REJECT = 'REJECT_CR_';

    /** Suffix dinamis tombol Tolak bila base URL Meta sama dengan Setujui (`…/setuju/{{6}}`). */
    public const REJECT_URL_PATH_PREFIX = 'reject-';

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

    public static function approveUrlButtonSuffix(string $interactionToken): string
    {
        return strtolower(trim($interactionToken));
    }

    public static function rejectUrlButtonSuffix(string $interactionToken): string
    {
        return self::REJECT_URL_PATH_PREFIX.self::approveUrlButtonSuffix($interactionToken);
    }

    /** URL lengkap tombol Setujui (parameter `{{1}}` template Meta = seluruh URL). */
    public static function approveAuthorizationFullUrl(string $interactionToken): string
    {
        return URL::route(
            'extern-cr.authorize.short',
            ['actionSuffix' => self::approveUrlButtonSuffix($interactionToken)],
            absolute: true,
        );
    }

    /** URL lengkap tombol Tolak (parameter `{{1}}` template Meta = seluruh URL). */
    public static function rejectAuthorizationFullUrl(string $interactionToken): string
    {
        return URL::route(
            'extern-cr.authorize.short',
            ['actionSuffix' => self::rejectUrlButtonSuffix($interactionToken)],
            absolute: true,
        );
    }

    /**
     * @return array{interaction_token: string, decision: string}|null
     */
    public static function decisionFromSetujuUrlSuffix(string $suffix): ?array
    {
        $suffix = strtolower(trim($suffix));
        if ($suffix === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9]{32}$/', $suffix)) {
            return [
                'interaction_token' => $suffix,
                'decision' => ExternCr::WA_AUTH_APPROVED,
            ];
        }

        if (str_starts_with($suffix, self::REJECT_URL_PATH_PREFIX)) {
            $token = substr($suffix, strlen(self::REJECT_URL_PATH_PREFIX));
            if (preg_match('/^[a-z0-9]{32}$/', $token)) {
                return [
                    'interaction_token' => $token,
                    'decision' => ExternCr::WA_AUTH_REJECTED,
                ];
            }
        }

        return null;
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
