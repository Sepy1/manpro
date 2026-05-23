<?php

namespace App\Support;

final class IndonesianWhatsappPhoneNormalizer
{
    /**
     * Normalkan input ke bentuk WhatsApp Cloud API Indonesia: digit saja tanpa "+", prefiks 628.
     * Menerima 08xxxxxxxxxx, 628xxxxxxxxxx, +62, spasi/pemisah.
     */
    public static function toWaDigits62(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($input));
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $normalized = '62'.$digits;
        } else {
            return null;
        }

        if (! preg_match('/^628\d{8,13}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
