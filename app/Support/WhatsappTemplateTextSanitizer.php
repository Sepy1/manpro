<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Menyesuaikan teks placeholder body template WhatsApp Cloud (Meta). */
final class WhatsappTemplateTextSanitizer
{
    /**
     * Meta menolak newline/tab dan lebih dari 4 spasi berurutan pada parameter body.
     * Baris baru (termasuk dari HTML) diganti koma + spasi.
     */
    public static function bodyParameter(string $text, int $maxLength = 900): string
    {
        $normalized = self::normalizeText($text);

        if ($normalized === '') {
            $normalized = '—';
        }

        return Str::limit($normalized, $maxLength, '…');
    }

    /** Placeholder URL template — hanya hapus karakter terlarang Meta, jangan ubah struktur URL. */
    public static function urlParameter(string $url, int $maxLength = 900): string
    {
        $normalized = trim($url);
        $normalized = preg_replace('/(?:\R|\t|\v|\f)+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\x{00a0}/u', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            $normalized = '—';
        }

        return Str::limit($normalized, $maxLength, '…');
    }

    /** @return non-empty-string|null Alasan Meta #132018 jika teks masih ditolak. */
    public static function metaRejectReason(string $text): ?string
    {
        if (preg_match('/(?:\R|\t|\v|\f)/u', $text)) {
            return 'mengandung newline/tab';
        }

        if (preg_match('/ {5,}/', $text)) {
            return 'lebih dari 4 spasi berurutan';
        }

        return null;
    }

    private static function normalizeText(string $text): string
    {
        $normalized = strip_tags($text);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/<br\s*\/?>/i', ', ', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?:\R|\t|\v|\f)+/u', ', ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\x{00a0}/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\p{Z}{5,}/u', '    ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\p{Z}+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/,\s*,+/', ', ', $normalized) ?? $normalized;

        return trim($normalized, " \t,");
    }
}
