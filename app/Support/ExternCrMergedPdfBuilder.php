<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\ExternCrAttachment;
use App\Models\ExternCrChangeReason;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ExternCrMergedPdfBuilder
{
    public static function streamedInlineResponse(ExternCr $externCr): Response
    {
        [$binary, $fileName] = self::mergedPdfBinary($externCr);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * @return array{0: string, 1: string} [binary, fileName]
     */
    public static function mergedPdfBinary(ExternCr $externCr): array
    {
        $externCr->load(['attachments', 'division', 'application', 'changeReason', 'creator', 'divisionsInvolved']);

        $reasonsForPdf = ExternCrChangeReason::query()
            ->where(function ($q) use ($externCr) {
                $q->where('is_active', true)
                    ->orWhere('id', $externCr->extern_cr_change_reason_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $logoPath = public_path('images/bkk.png');
        $logoDataUri = null;
        if (is_readable($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        $creatorSignedUrl = ExternCrPdfQr::signedVerifyUrl($externCr, ExternCrPdfQr::PURPOSE_CREATOR);
        $approverSignedUrl = ExternCrPdfQr::signedVerifyUrl($externCr, ExternCrPdfQr::PURPOSE_APPROVER);

        $divisiTerlibatDisplay = trim((string) ($externCr->divisions_terlibat_text ?? ''));
        if ($divisiTerlibatDisplay === '' && $externCr->divisionsInvolved->isNotEmpty()) {
            $divisiTerlibatDisplay = $externCr->divisionsInvolved->sortBy('name')->pluck('name')->implode(', ');
        }

        $pdf = Pdf::loadView('pages.dashboard.cr-eksternal.pdf-permintaan-perubahan', [
            'cr' => $externCr,
            'reasonsForPdf' => $reasonsForPdf,
            'logoDataUri' => $logoDataUri,
            'qrCreatorDataUri' => ExternCrPdfQr::dataUriForUrl($creatorSignedUrl),
            'qrApproverDataUri' => ExternCrPdfQr::dataUriForUrl($approverSignedUrl),
            'divisiTerlibatDisplay' => $divisiTerlibatDisplay !== '' ? $divisiTerlibatDisplay : '—',
        ])->setPaper('a4', 'portrait');

        $mainBinary = $pdf->output(['compress' => false]);

        $fileName = 'CR-'.$externCr->nomor.'.pdf';

        $sortedPdfAttachments = $externCr->attachments
            ->sortBy(fn (ExternCrAttachment $a) => [$a->position, $a->id]);

        $paths = [];
        foreach ($sortedPdfAttachments as $attachment) {
            if (! self::attachmentIsPdfForMerge($attachment)) {
                continue;
            }

            try {
                $absolute = Storage::disk($attachment->disk)->path($attachment->path);
            } catch (\Throwable) {
                continue;
            }

            if (is_readable($absolute)) {
                $paths[] = $absolute;
            }
        }

        if ($paths === []) {
            return [$mainBinary, $fileName];
        }

        try {
            $merged = ExternCrPrintedPdfAssembler::mergeMainWithPdfAttachments($mainBinary, $paths);

            return [$merged, $fileName];
        } catch (\Throwable $e) {
            Log::warning('CR PDF: gagal gabung lampiran PDF, hanya formulir utama yang dikeluarkan.', [
                'extern_cr_id' => $externCr->id,
                'nomor' => $externCr->nomor,
                'message' => $e->getMessage(),
            ]);

            return [$mainBinary, $fileName];
        }
    }

    public static function attachmentIsPdf(ExternCrAttachment $attachment): bool
    {
        return self::attachmentIsPdfForMerge($attachment);
    }

    private static function attachmentIsPdfForMerge(ExternCrAttachment $attachment): bool
    {
        $nameExt = strtolower((string) pathinfo((string) ($attachment->original_name ?: ''), PATHINFO_EXTENSION));
        if ($nameExt === 'pdf') {
            return true;
        }

        $pathExt = strtolower((string) pathinfo(basename((string) $attachment->path), PATHINFO_EXTENSION));
        if ($pathExt === 'pdf') {
            return true;
        }

        return str_contains(Str::lower((string) ($attachment->mime ?? '')), 'pdf');
    }
}
