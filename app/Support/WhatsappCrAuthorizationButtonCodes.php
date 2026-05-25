<?php

namespace App\Support;

use App\Models\ExternCr;
use Illuminate\Support\Facades\URL;

/**
 * Payload quick reply (legacy) dan suffix URL tombol CTA template Meta.
 *
 * Template `konfirmasi_cr_manpro`:
 * - body {{1}} nomor CR, {{2}} nama CR, {{3}} pembuat, {{4}} daftar perubahan
 * - tombol «Tindak Lanjut»: base Meta `https://manpro.bkkjateng.co.id/approval/{{1}}`
 *   → Laravel mengisi {{1}} tombol dengan `interaction_token` (32 char)
 * - tombol «Lihat CR»: base Meta `https://manpro.bkkjateng.co.id/viewcr/{{1}}`
 *   → Laravel mengisi {{1}} tombol dengan nomor CR
 */
final class WhatsappCrAuthorizationButtonCodes
{
    public const PREFIX_APPROVE = 'APPROVE_CR_';

    public const PREFIX_REJECT = 'REJECT_CR_';

    /** Suffix dinamis tombol Tolak (mode dua tombol URL legacy). */
    public const REJECT_URL_PATH_PREFIX = 'reject-';

    /** @deprecated Hanya untuk webhook pesan lama */
    private const LEGACY_PREFIX_APPROVE = 'APR_';

    /** @deprecated Hanya untuk webhook pesan lama */
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

    /** Suffix URL tombol «Tindak Lanjut» (`…/approval/{{1}}` di Meta). */
    public static function approvalLandingUrlSuffix(string $interactionToken): string
    {
        return self::approveUrlButtonSuffix($interactionToken);
    }

    /** Suffix URL tombol «Lihat CR» (`…/viewcr/{{1}}` di Meta). */
    public static function viewCrUrlSuffix(ExternCr $externCr): string
    {
        $nomor = trim((string) ($externCr->nomor ?? ''));
        if ($nomor === '') {
            $nomor = trim((string) ($externCr->nama ?? ''));
        }
        if ($nomor === '') {
            $nomor = (string) $externCr->id;
        }

        return strtolower($nomor);
    }

    /** URL lengkap halaman tindak lanjut otorisasi. */
    public static function approvalLandingFullUrl(string $interactionToken): string
    {
        return URL::route(
            'extern-cr.authorize.approval',
            ['interactionToken' => self::approvalLandingUrlSuffix($interactionToken)],
            absolute: true,
        );
    }

    /** @deprecated Mode dua tombol URL — Setujui langsung */
    public static function approveAuthorizationFullUrl(string $interactionToken): string
    {
        return URL::route(
            'extern-cr.authorize.short',
            ['actionSuffix' => self::approveUrlButtonSuffix($interactionToken)],
            absolute: true,
        );
    }

    /** @deprecated Mode dua tombol URL — Tolak langsung */
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
