<?php

use App\Models\ExternCr;
use App\Support\MahadataWhatsappExternCrAuthorizationNotifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'mahadata:test-cr-auth-template {extern_cr_id : ID baris extern_crs} {--to= : Nomor WA (mis. 62812…)}',
    function (string $extern_cr_id): int {
        $to = trim((string) $this->option('to'));
        if ($to === '') {
            $this->error('Wajib --to=628xxxxxxxxxx (format nomor seperti produksi).');

            return self::FAILURE;
        }

        $id = ctype_digit($extern_cr_id) ? (int) $extern_cr_id : 0;
        if ($id < 1) {
            $this->error('extern_cr_id harus bilangan positif.');

            return self::FAILURE;
        }

        $cr = ExternCr::query()->find($id);
        if ($cr === null) {
            $this->error("ExternCr id={$id} tidak ditemukan.");

            return self::FAILURE;
        }

        $ok = app(MahadataWhatsappExternCrAuthorizationNotifier::class)->sendTestCrAuthorizationTemplate($cr, $to);

        if ($ok) {
            $this->info('OK: penyaluran dikonfirmasi (messages[0].id dari respons). Cek WhatsApp penerima & log Laravel.');

            return self::SUCCESS;
        }

        $this->warn('Gagal / tidak ada messages[0].id — lihat log "Mahadata CR auth WA (uji)" untuk body respons.');

        return self::FAILURE;
    }
)->purpose('Uji kirim template otorisasi CR (tanpa dispatch / quick reply) ke satu nomor.');
