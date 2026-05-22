<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

final class ExternCrPrintedPdfAssembler
{
    /**
     * Sisipkan setiap lampiran PDF setelah formulir utama.
     *
     * @param  iterable<string>  $absolutePdfPaths  Path fisik pada disk yang dapat dibaca
     *
     * @throws \Throwable
     */
    public static function mergeMainWithPdfAttachments(string $mainPdfBinary, iterable $absolutePdfPaths): string
    {
        $fpdi = new Fpdi;
        $dir = sys_get_temp_dir();
        $mainPath = $dir.'/cr_form_'.str_replace('.', '', uniqid('', true)).'.pdf';

        file_put_contents($mainPath, $mainPdfBinary);

        try {
            self::appendAllPagesFromFile($fpdi, $mainPath);

            foreach ($absolutePdfPaths as $path) {
                $path = (string) $path;
                if ($path !== '' && is_readable($path)) {
                    self::appendAllPagesFromFile($fpdi, $path);
                }
            }

            return (string) $fpdi->Output('S');
        } finally {
            @unlink($mainPath);
        }
    }

    private static function appendAllPagesFromFile(Fpdi $fpdi, string $absolutePath): void
    {
        $pageCount = $fpdi->setSourceFile($absolutePath);

        for ($page = 1; $page <= $pageCount; $page++) {
            $tplIdx = $fpdi->importPage($page);
            $size = $fpdi->getTemplateSize($tplIdx);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            if (! empty($size['orientation']) && in_array($size['orientation'], ['P', 'L'], true)) {
                $orientation = $size['orientation'];
            }
            $fpdi->AddPage($orientation, [$size['width'], $size['height']]);
            $fpdi->useTemplate($tplIdx);
        }
    }
}
