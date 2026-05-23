<?php

namespace App\Http\Controllers;

use App\Models\ExternCr;
use App\Support\ExternCrMergedPdfBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternCrSignedPdfDownloadController extends Controller
{
    /**
     * Unduh gabungan PDF utama + lampiran PDF (titik akses bermaterai oleh URL bertanda kedaluwarsa).
     */
    public function __invoke(Request $request, ExternCr $externCr): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        return ExternCrMergedPdfBuilder::streamedInlineResponse($externCr);
    }
}
