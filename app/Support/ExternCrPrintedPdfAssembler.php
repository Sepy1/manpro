<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ExternCrPrintedPdfAssembler
{
    /**
     * Sisipkan lampiran PDF setelah formulir utama.
     *
     * Urutan penyatuan CLI (pdfunite / qpdf) dipakai dulu — kompatibel dengan PDF DomPDF —
     * kemudian fallback FPDI. PDF utama dari DomPDF sebaiknya dihasilkan dengan
     * `output(['compress' => false])` agar FPDI tidak gagal membaca stream terkompresi.
     *
     * @param  list<string>  $absolutePdfPaths
     *
     * @throws \Throwable
     */
    public static function mergeMainWithPdfAttachments(string $mainPdfBinary, array $absolutePdfPaths): string
    {
        $dir = sys_get_temp_dir();
        $uniq = strtolower(str_replace(['.', '+', '/', '='], '', bin2hex(random_bytes(9))));
        $mainPath = $dir.'/cr_main_'.$uniq.'.pdf';

        file_put_contents($mainPath, $mainPdfBinary);

        try {
            $paths = [];
            foreach ($absolutePdfPaths as $p) {
                $p = (string) $p;
                if ($p !== '' && is_readable($p)) {
                    $paths[] = $p;
                }
            }

            $mergedCliPath = null;
            if ($paths !== []) {
                $mergedCliPath = self::mergeWithCliUtilities($uniq, $mainPath, $paths);
            }

            if ($mergedCliPath !== null && is_readable($mergedCliPath)) {
                try {
                    return (string) file_get_contents($mergedCliPath);
                } finally {
                    @unlink($mergedCliPath);
                }
            }

            return self::mergeWithFpdi($mainPath, $paths);
        } finally {
            @unlink($mainPath);
        }
    }

    /**
     * @param  list<string>  $attachmentPathsReadable
     */
    private static function mergeWithCliUtilities(string $uniq, string $mainPath, array $attachmentPathsReadable): ?string
    {
        $outPath = sys_get_temp_dir().'/cr_merged_'.$uniq.'.pdf';
        @unlink($outPath);

        $finder = new ExecutableFinder;

        $pdfUnite = $finder->find('pdfunite');
        if ($pdfUnite !== null) {
            $command = array_merge([$pdfUnite, $mainPath], $attachmentPathsReadable, [$outPath]);
            $process = new Process($command);
            $process->setTimeout(180);
            $process->run();
            if ($process->isSuccessful() && is_readable($outPath) && filesize($outPath) > 32) {
                return $outPath;
            }
            Log::debug('PDF merge pdfunite failed', [
                'exit' => $process->getExitCode(),
                'err' => $process->getErrorOutput(),
                'std' => $process->getOutput(),
            ]);
            @unlink($outPath);
        }

        $qpdf = $finder->find('qpdf');
        if ($qpdf !== null) {
            $args = [$qpdf, '--empty', '--pages', $mainPath, '1-z'];
            foreach ($attachmentPathsReadable as $attachmentPath) {
                $args[] = $attachmentPath;
                $args[] = '1-z';
            }
            $args[] = '--';
            $args[] = $outPath;

            $process = new Process($args);
            $process->setTimeout(180);
            $process->run();
            if ($process->isSuccessful() && is_readable($outPath) && filesize($outPath) > 32) {
                return $outPath;
            }
            Log::debug('PDF merge qpdf failed', [
                'exit' => $process->getExitCode(),
                'err' => $process->getErrorOutput(),
            ]);
            @unlink($outPath);
        }

        return null;
    }

    /**
     * @param  list<string>  $absolutePdfPaths
     *
     * @throws \Throwable
     */
    private static function mergeWithFpdi(string $mainPdfPathOnDisk, array $absolutePdfPaths): string
    {
        $fpdi = new Fpdi;

        self::appendAllPagesFromFile($fpdi, $mainPdfPathOnDisk);

        foreach ($absolutePdfPaths as $path) {
            self::appendAllPagesFromFile($fpdi, $path);
        }

        return (string) $fpdi->Output('S');
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
