<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\User;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Kirim template konfirmasi ke otorisator setelah keputusan Setuju/Tolak (webhook). */
final class MahadataWhatsappAuthorizationConfirmationSender
{
    public function sendAfterDecision(ExternCr $externCr, User $authorizer, string $decision): bool
    {
        if (! (bool) config('services.mahadata_whatsapp.cr_authorization_confirmation_enabled', true)) {
            return false;
        }

        $endpoint = trim((string) config('services.mahadata_whatsapp.endpoint'));
        $token = trim((string) config('services.mahadata_whatsapp.token'));
        $template = trim((string) config('services.mahadata_whatsapp.cr_authorization_confirmation_template_name'));

        if ($endpoint === '' || $token === '' || $template === '') {
            Log::notice('Mahadata CR konfirmasi WA: endpoint/token/template konfirmasi kosong, dilewati.');

            return false;
        }

        $digits = IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim((string) ($authorizer->phone ?? '')));
        if ($digits === null) {
            Log::warning('Mahadata CR konfirmasi WA: nomor otorisator tidak valid.', ['user_id' => $authorizer->id]);

            return false;
        }

        $bodyParams = $this->confirmationTemplateBodyParameters($externCr, $decision);
        $language = (string) config(
            'services.mahadata_whatsapp.cr_authorization_confirmation_template_language_code',
            config('services.mahadata_whatsapp.cr_authorization_template_language_code', 'id')
        );

        $response = $this->postBodyOnlyTemplate($endpoint, $token, $template, $digits, $bodyParams, $language);

        if ($this->outboundLooksSuccessful($response)) {
            Log::info('Mahadata CR konfirmasi WA: template konfirmasi terkirim.', [
                'extern_cr_id' => $externCr->id,
                'user_id' => $authorizer->id,
                'decision' => $decision,
                'template' => $template,
            ]);

            return true;
        }

        Log::warning('Mahadata CR konfirmasi WA: gagal kirim template konfirmasi.', [
            'extern_cr_id' => $externCr->id,
            'user_id' => $authorizer->id,
            'template' => $template,
            'status' => $response?->status(),
            'body' => $response?->body(),
        ]);

        return false;
    }

    /**
     * Template `konfirmasi_otorisasi_manpro`: {{1}} nama CR, {{2}} Disetujui/Ditolak, {{3}} link PDF bundel (sama {{4}} permintaan otorisasi).
     *
     * @return list<array{type: string, text: string}>
     */
    private function confirmationTemplateBodyParameters(ExternCr $externCr, string $decision): array
    {
        $crLabel = trim((string) ($externCr->nama ?? ''));
        if ($crLabel === '') {
            $crLabel = trim((string) ($externCr->nomor ?? ''));
        }
        if ($crLabel === '') {
            $crLabel = '—';
        }

        $actionLabel = match ($decision) {
            ExternCr::WA_AUTH_APPROVED => 'Disetujui',
            ExternCr::WA_AUTH_REJECTED => 'Ditolak',
            default => 'Diproses',
        };

        $pdfUrl = ExternCrPdfQr::temporarySignedPdfBundleUrl($externCr);

        return [
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($crLabel)],
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($actionLabel)],
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($pdfUrl)],
        ];
    }

    /**
     * @param  list<array{type: string, text: string}>  $bodyTextParameters
     */
    private function postBodyOnlyTemplate(
        string $endpoint,
        string $token,
        string $templateName,
        string $toDigits62,
        array $bodyTextParameters,
        string $language,
    ): ?HttpClientResponse {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toDigits62,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $bodyTextParameters,
                    ],
                ],
            ],
        ];

        try {
            $timeout = max(10, min(120, (int) config('services.mahadata_whatsapp.timeout_seconds', 30)));

            return Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($endpoint, $payload);
        } catch (Throwable $e) {
            Log::warning('Mahadata CR konfirmasi WA: request gagal.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function outboundLooksSuccessful(?HttpClientResponse $response): bool
    {
        if ($response === null || ! $response->successful()) {
            return false;
        }

        $data = $response->json();
        if (! is_array($data) || ! array_key_exists('error', $data)) {
            return true;
        }

        $error = $data['error'];

        return $error === null || $error === false || $error === '' || $error === [];
    }
}
