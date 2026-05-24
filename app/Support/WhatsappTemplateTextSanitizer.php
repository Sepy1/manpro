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
        $normalized = strip_tags($text);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/<br\s*\/?>/i', ', ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[\R\t\v\f\x0B]+/', ', ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\x{00a0}/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\p{Z}{5,}/u', '    ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\p{Z}+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/,\s*,+/', ', ', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \t,");

        if ($normalized === '') {
            $normalized = '—';
        }

        return Str::limit($normalized, $maxLength, '…');
    }
}
