<?php

namespace App\Support;

use App\Models\ExternCr;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Facades\URL;

final class ExternCrPdfQr
{
    public const PURPOSE_CREATOR = 'creator';

    public const PURPOSE_APPROVER = 'approver';

    public static function signedVerifyUrl(ExternCr $externCr, string $purpose): string
    {
        return URL::signedRoute('extern-cr.verify', [
            'externCr' => $externCr,
            'purpose' => $purpose,
        ], absolute: true);
    }

    /** Link unduh bundel PDF CR (sama placeholder {{4}} template permintaan otorisasi WA). */
    public static function temporarySignedPdfBundleUrl(ExternCr $externCr): string
    {
        $ttlMinutes = max(
            1,
            (int) config('services.extern_cr.signed_pdf_url_ttl_minutes', 60 * 24 * 7)
        );

        return URL::temporarySignedRoute(
            'extern-cr.signed-pdf',
            now()->addMinutes($ttlMinutes),
            ['externCr' => $externCr],
            absolute: true
        );
    }

    public static function dataUriForUrl(string $url): string
    {
        $result = (new Builder)->build(
            data: $url,
            size: 108,
            margin: 2,
        );

        return $result->getDataUri();
    }
}
