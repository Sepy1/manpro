<?php

use App\Models\ExternCr;
use App\Support\MahadataWhatsappExternCrAuthorizationNotifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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

Artisan::command(
    'webhook:simulate-cr-auth {dispatch_id : ID baris whatsapp_cr_authorization_dispatches}',
    function (string $dispatch_id): int {
        $id = ctype_digit($dispatch_id) ? (int) $dispatch_id : 0;
        if ($id < 1) {
            $this->error('dispatch_id harus bilangan positif.');

            return self::FAILURE;
        }

        $dispatch = \App\Models\WhatsappCrAuthorizationDispatch::query()->find($id);
        if ($dispatch === null) {
            $this->error("Dispatch id={$id} tidak ditemukan.");

            return self::FAILURE;
        }

        $token = (string) ($dispatch->interaction_token ?? '');
        if ($token === '') {
            $this->error('Dispatch tidak punya interaction_token (kirim ulang WA otorisasi).');

            return self::FAILURE;
        }

        $payload = [
            'type' => 'whatsapp.inbound_message.received',
            'whatsappInboundMessage' => [
                'from' => $dispatch->recipient_wa_id,
                'type' => 'button',
                'button' => [
                    'payload' => \App\Support\WhatsappCrAuthorizationButtonCodes::approvePayload($token),
                    'text' => 'Setujui',
                ],
                'context' => [
                    'id' => $dispatch->wam_id ?? 'wamid.test',
                ],
            ],
        ];

        $url = rtrim((string) config('app.url'), '/').'/webhook/whatsapp';
        $this->line("POST {$url}");

        $response = \Illuminate\Support\Facades\Http::acceptJson()
            ->asJson()
            ->post($url, $payload);

        $this->line('HTTP '.$response->status());
        $this->line($response->body() !== '' ? $response->body() : '(empty body)');

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
)->purpose('Simulasikan webhook Mahadata/YCloud tombol Setujui untuk satu dispatch (uji lokal/production).');

Artisan::command(
    'whatsapp:cr-auth-inspect {target : extern_cr_id atau dispatch_id} {--dispatch : target adalah dispatch_id}',
    function (string $target): int {
        $id = ctype_digit($target) ? (int) $target : 0;
        if ($id < 1) {
            $this->error('ID harus bilangan positif.');

            return self::FAILURE;
        }

        $dispatch = (bool) $this->option('dispatch')
            ? \App\Models\WhatsappCrAuthorizationDispatch::query()->with(['externCr', 'user'])->find($id)
            : \App\Models\WhatsappCrAuthorizationDispatch::query()
                ->with(['externCr', 'user'])
                ->where('extern_cr_id', $id)
                ->orderByDesc('id')
                ->first();

        if ($dispatch === null) {
            $this->error('Dispatch tidak ditemukan.');

            return self::FAILURE;
        }

        $cr = $dispatch->externCr;
        if ($cr === null) {
            $this->error('ExternCr tidak ditemukan untuk dispatch ini.');

            return self::FAILURE;
        }

        $cr->loadMissing('creator');
        $deskripsi = trim((string) ($cr->deskripsi_permintaan ?? ''));
        if ($deskripsi === '') {
            $deskripsi = trim((string) ($cr->perubahan_diharapkan ?? ''));
        }

        $pdfUrl = \App\Support\ExternCrPdfQr::temporarySignedPdfBundleUrl($cr);
        $params = [
            'judul' => \App\Support\WhatsappTemplateTextSanitizer::bodyParameter(trim((string) ($cr->nama ?: $cr->nomor ?: '—'))),
            'pembuat' => \App\Support\WhatsappTemplateTextSanitizer::bodyParameter(trim((string) ($cr->creator?->name ?: 'Belum ditetapkan'))),
            'deskripsi' => \App\Support\WhatsappTemplateTextSanitizer::bodyParameter($deskripsi !== '' ? $deskripsi : '—'),
            'pdf_url' => \App\Support\WhatsappTemplateTextSanitizer::urlParameter($pdfUrl),
        ];

        $this->line('=== Diagnosa otorisasi CR via WhatsApp ===');
        $this->line('APP_URL: '.config('app.url'));
        $this->line('Webhook: '.rtrim((string) config('app.url'), '/').'/webhook/whatsapp');
        $this->line('');
        $this->line("Dispatch #{$dispatch->id} | CR #{$cr->id} ({$cr->nomor})");
        $this->line('Keputusan CR: '.($cr->wa_authorization_decision ?? '(belum ada)'));
        $this->line('User otorisator: #'.$dispatch->user_id.' '.($dispatch->user?->name ?? '—'));
        $this->line('WA penerima: '.$dispatch->recipient_wa_id);
        $this->line('wam_id tersimpan: '.($dispatch->wam_id ?? '(kosong)'));
        $this->line('interaction_token: '.($dispatch->interaction_token ? Str::limit($dispatch->interaction_token, 12).'…' : '(kosong)'));
        $this->line('');

        foreach ($params as $key => $value) {
            $reason = \App\Support\WhatsappTemplateTextSanitizer::metaRejectReason($value);
            $flag = $reason ? " [TOLAK META: {$reason}]" : ' [OK]';
            $this->line(strtoupper($key).$flag);
            $this->line('  len='.strlen($value).' | '.Str::limit($value, 140));
        }

        $this->line('');
        if ($cr->wa_authorization_decision !== null) {
            $this->warn('CR sudah punya keputusan — UI admin tidak bisa kirim ulang WA ke otorisator yang sama.');
            $this->line('Untuk uji kirim+klik tombol asli: reset keputusan dulu, lalu kirim ulang dari admin.');
        } else {
            $this->info('CR belum ada keputusan — kirim ulang WA dari admin masih bisa.');
        }

        $this->line('');
        $this->line('Uji kirim body-only (tanpa tombol):');
        $this->line("  php artisan mahadata:test-cr-auth-template {$cr->id} --to={$dispatch->recipient_wa_id}");
        $this->line('Uji webhook tombol Setujui (backend saja):');
        $this->line("  php artisan webhook:simulate-cr-auth {$dispatch->id}");

        return self::SUCCESS;
    }
)->purpose('Diagnosa dispatch CR auth WA: parameter template, keputusan CR, dan langkah uji.');
