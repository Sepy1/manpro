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
