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
