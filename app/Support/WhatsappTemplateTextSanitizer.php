<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Menyesuaikan teks placeholder body template WhatsApp Cloud (Meta). */
final class WhatsappTemplateTextSanitizer
{
    /**
     * Meta menolak newline/tab dan lebih dari 4 spasi berurutan pada parameter body.
     * Baris baru di deskripsi dll. diganti koma + spasi.
     */
    public static function bodyParameter(string $text, int $maxLength = 900): string
    {
        $normalized = str_replace(["\r\n", "\r", "\n", "\t"], ', ', $text);
        $normalized = preg_replace('/,\s*,+/', ', ', $normalized) ?? $normalized;
        $normalized = preg_replace('/ {5,}/', '    ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[ \t]+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \t,");

        if ($normalized === '') {
            $normalized = '—';
        }

        return Str::limit($normalized, $maxLength, '…');
    }
}
