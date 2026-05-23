<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class MahadataWhatsappOtpSender
{
    /** @return array{0: string, 1: string, 2: string} endpoint, token, template */
    private function configCore(): array
    {
        return [
            trim((string) config('services.mahadata_whatsapp.endpoint')),
            trim((string) config('services.mahadata_whatsapp.token')),
            trim((string) config('services.mahadata_whatsapp.template_name')),
        ];
    }

    public function send(string $waRecipientDigits62, string $otpPlain): bool
    {
        [$endpoint, $token, $template] = $this->configCore();

        if ($endpoint === '' || $token === '' || $template === '') {
            Log::warning('Mahadata WhatsApp 2FA: konfigurasi endpoint/token/template kosong.', [
                'has_endpoint' => $endpoint !== '',
                'has_token' => $token !== '',
                'has_template' => $template !== '',
            ]);

            return false;
        }

        $otp = trim($otpPlain);
        if ($otp === '') {
            return false;
        }

        $language = (string) config('services.mahadata_whatsapp.template_language_code', 'id');
        $mode = strtolower((string) config('services.mahadata_whatsapp.otp_send_mode', 'auto'));

        $bodyOnly = $this->buildPayload($waRecipientDigits62, $otp, $template, $language, includeUrlButton: false);
        $withUrl = $this->buildPayload($waRecipientDigits62, $otp, $template, $language, includeUrlButton: true);

        return match ($mode) {
            'body_only', 'body', 'minimal' => $this->attemptSend($endpoint, $token, $bodyOnly, 'body_only'),

            'full', 'url', 'with_url' => $this->attemptSend($endpoint, $token, $withUrl, 'full'),

            default => $this->sendAutoMode($endpoint, $token, $withUrl, $bodyOnly),
        };
    }

    private function sendAutoMode(string $endpoint, string $token, array $payloadFull, array $payloadBody): bool
    {
        $resp = $this->postMahadata($endpoint, $token, $payloadFull);
        if ($this->responseIndicatesSuccess($resp)) {
            return true;
        }

        Log::notice('Mahadata WhatsApp 2FA: kirim full (body+tombol URL) gagal, mencoba hanya komponen body.', [
            'template' => trim((string) config('services.mahadata_whatsapp.template_name')),
            'endpoint_host' => $this->safeHostFromUrl($endpoint),
            'http_status_first' => $resp?->status(),
            'error_snippet_first' => $this->errorSnippetFromResponse($resp),
        ]);

        $resp2 = $this->postMahadata($endpoint, $token, $payloadBody);
        if ($this->responseIndicatesSuccess($resp2)) {
            Log::info('Mahadata WhatsApp 2FA: kirim berhasil dengan mode body-only.', [
                'template' => trim((string) config('services.mahadata_whatsapp.template_name')),
            ]);

            return true;
        }

        $this->logFailedResponse($resp2 ?? $resp, 'body_only_fallback');

        return false;
    }

    private function attemptSend(string $endpoint, string $token, array $payload, string $label): bool
    {
        $resp = $this->postMahadata($endpoint, $token, $payload);

        if ($this->responseIndicatesSuccess($resp)) {
            return true;
        }

        $this->logFailedResponse($resp, $label);

        return false;
    }

    /**
     * @return array{messaging_product: string, to: string, type: string, template: array<string, mixed>}
     */
    private function buildPayload(
        string $waRecipientDigits62,
        string $otp,
        string $template,
        string $language,
        bool $includeUrlButton,
    ): array {
        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $otp],
                ],
            ],
        ];

        if ($includeUrlButton) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $otp],
                ],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $waRecipientDigits62,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];
    }

    private function postMahadata(string $endpoint, string $token, array $payload): ?Response
    {
        try {
            $timeout = max(10, min(120, (int) config('services.mahadata_whatsapp.timeout_seconds', 30)));

            return Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($endpoint, $payload);
        } catch (Throwable $e) {
            Log::warning('Mahadata WhatsApp 2FA: request gagal (timeout/jaringan).', [
                'message' => $e->getMessage(),
                'endpoint_host' => $this->safeHostFromUrl($endpoint),
            ]);

            return null;
        }
    }

    private function responseIndicatesSuccess(?Response $response): bool
    {
        if ($response === null) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return true;
        }

        // Meta WhatsApp biasanya tidak punya key `error` saat OK. Proxy Mahadata kadang menyertakan
        // `"error": false` / null / kosong walau pengiriman sukses; jangan salah anggap sebagai gagal.
        if (! array_key_exists('error', $data)) {
            return true;
        }

        $error = $data['error'];

        return $error === null
            || $error === false
            || $error === ''
            || $error === [];
    }

    private function logFailedResponse(?Response $response, string $attempt): void
    {
        if ($response === null) {
            return;
        }

        Log::warning('Mahadata WhatsApp 2FA: kirim gagal.', [
            'attempt' => $attempt,
            'http_status' => $response->status(),
            'body' => $response->body(),
            'endpoint_host' => $this->safeHostFromUrl(trim((string) config('services.mahadata_whatsapp.endpoint'))),
            'template' => (string) config('services.mahadata_whatsapp.template_name'),
            'snippet' => $this->errorSnippetFromResponse($response),
        ]);
    }

    private function errorSnippetFromResponse(?Response $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $json = $response->json();
        if (is_array($json) && isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }

        return Str::limit($response->body(), 280);
    }

    private function safeHostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : '—';
    }
}
