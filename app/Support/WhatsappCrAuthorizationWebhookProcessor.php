<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\User;
use App\Models\WhatsappCrAuthorizationDispatch;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class WhatsappCrAuthorizationWebhookProcessor
{
    public function isSubscriptionVerification(Request $request): bool
    {
        if ($this->hubParameter($request, 'mode') !== 'subscribe') {
            return false;
        }

        return $this->hubParameter($request, 'challenge') !== '';
    }

    public function verifySubscription(Request $request): Response
    {
        if ($this->hubParameter($request, 'mode') !== 'subscribe') {
            return response('Forbidden', 403);
        }

        $expected = trim((string) config('services.whatsapp.webhook_verify_token'));
        $token = $this->hubParameter($request, 'verify_token');

        if ($expected === '') {
            Log::warning('Webhook WhatsApp: WHATSAPP_WEBHOOK_VERIFY_TOKEN kosong — verifikasi ditolak.');

            return response('Forbidden', 403);
        }

        if (! hash_equals($expected, $token)) {
            Log::notice('Webhook WhatsApp: verify token tidak cocok.', [
                'method' => $request->method(),
            ]);

            return response('Forbidden', 403);
        }

        $challenge = $this->hubParameter($request, 'challenge');

        Log::info('Webhook WhatsApp: verifikasi subscribe berhasil.', [
            'method' => $request->method(),
        ]);

        return response($challenge, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Meta/Mahadata bisa mengirim hub.* lewat query string, form, atau JSON (hub.mode / hub_mode).
     */
    private function hubParameter(Request $request, string $suffix): string
    {
        $candidates = [
            'hub.'.$suffix,
            'hub_'.$suffix,
            $suffix,
        ];

        foreach ($candidates as $key) {
            $fromQuery = $request->query($key);
            if (is_scalar($fromQuery) && trim((string) $fromQuery) !== '') {
                return trim((string) $fromQuery);
            }

            $fromInput = $request->input($key);
            if (is_scalar($fromInput) && trim((string) $fromInput) !== '') {
                return trim((string) $fromInput);
            }
        }

        foreach (['hub.'.$suffix, $suffix] as $path) {
            $nested = data_get($request->all(), $path);
            if (is_scalar($nested) && trim((string) $nested) !== '') {
                return trim((string) $nested);
            }
        }

        return '';
    }

    public function handleInbound(Request $request): void
    {
        $raw = $request->getContent();

        if (! $this->signatureIsValid($request, $raw)) {
            abort(403, 'Invalid signature');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        foreach ($this->iterateButtonReplyMessages($payload) as ['message' => $message]) {
            $this->processButtonReply($message);
        }
    }

    private function signatureIsValid(Request $request, string $rawBody): bool
    {
        if ((bool) config('services.whatsapp.skip_signature_validation', false)) {
            Log::notice('Webhook WhatsApp: pemeriksaan tanda tangan dinonaktifkan (WHATSAPP_WEBHOOK_SKIP_SIGNATURE_VALIDATE).');

            return true;
        }

        $secret = trim((string) config('services.whatsapp.meta_app_secret'));
        if ($secret === '') {
            Log::notice('Webhook WhatsApp: WHATSAPP_APP_SECRET kosong — payload diterima tanpa X-Hub-Signature-256 (sesuai konfigurasi Anda).');

            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! Str::startsWith($header, 'sha256=')) {
            return false;
        }

        $expectedMac = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals('sha256='.$expectedMac, $header);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Generator<int, array{message: array<string, mixed>}>
     */
    private function iterateButtonReplyMessages(array $payload): \Generator
    {
        $entries = $payload['entry'] ?? [];
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach (($entry['changes'] ?? []) as $change) {
                if (! is_array($change)) {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }
                foreach (($value['messages'] ?? []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }
                    yield ['message' => $message];
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function processButtonReply(array $message): void
    {
        $inboundId = (string) ($message['id'] ?? '');
        if ($inboundId !== '') {
            if (! Cache::add('whatsapp:inbound_msg:'.$inboundId, 1, now()->addDays(7))) {
                return;
            }
        }

        if (($message['type'] ?? '') !== 'interactive') {
            return;
        }

        if (data_get($message, 'interactive.type') !== 'button_reply') {
            return;
        }

        $btnPayloadId = trim((string) data_get($message, 'interactive.button_reply.id', ''));
        $title = trim((string) data_get($message, 'interactive.button_reply.title', ''));

        if ($btnPayloadId === '' && $title === '') {
            return;
        }

        $fromRaw = (string) ($message['from'] ?? '');
        $fromDigits = IndonesianWhatsappPhoneNormalizer::toWaDigits62($fromRaw);
        if ($fromDigits === null) {
            Log::notice('Webhook WhatsApp: pengirim bukan nomor format yang dikenali.', ['from' => $fromRaw]);

            return;
        }

        $contextWamId = (string) data_get($message, 'context.id', '');

        $auditReference = $btnPayloadId !== '' ? $btnPayloadId : $title;

        /** @var WhatsappCrAuthorizationDispatch|null $dispatch */
        $dispatch = null;
        $decision = null;

        $parsedPayload = WhatsappCrAuthorizationButtonCodes::tokenDecisionFromPayloadId($btnPayloadId);
        if ($parsedPayload !== null) {
            $dispatch = WhatsappCrAuthorizationDispatch::query()
                ->where('interaction_token', $parsedPayload['interaction_token'])
                ->where('recipient_wa_id', $fromDigits)
                ->first();
            $decision = $parsedPayload['decision'];

            if ($dispatch === null) {
                Log::notice('Webhook WhatsApp: payload APPROVE_CR_/REJECT_CR_ (atau APR_/REJ_ lama) tidak cocok baris kiriman atau nomor penerima salah.', [
                    'interaction_token_snip' => Str::limit((string) ($parsedPayload['interaction_token'] ?? ''), 12, '…'),
                ]);

                return;
            }
        } else {
            $dispatch = $this->resolveDispatch($fromDigits, $contextWamId);
            if ($dispatch === null) {
                Log::notice('Webhook WhatsApp: tidak menemukan dispatch CR untuk tombol.', [
                    'from' => $fromDigits,
                    'context_wam_id' => $contextWamId ?: null,
                    'button_id' => $btnPayloadId ?: null,
                    'button_title' => $title ?: null,
                ]);

                return;
            }
            $decision = $this->normalizeDecisionTitle($title);
            if ($decision === null) {
                Log::info('Webhook WhatsApp: tidak ada payload APPROVE_CR_/REJECT_CR_ dan judul tombol tidak dipetakan.', [
                    'title' => $title,
                    'button_id' => $btnPayloadId ?: null,
                ]);

                return;
            }
        }

        $user = User::query()->find($dispatch->user_id);
        if ($user === null || ! $user->can_authorize_extern_cr) {
            Log::notice('Webhook WhatsApp: pengguna tidak berhak otorisasi.', ['dispatch_id' => $dispatch->id]);

            return;
        }

        $userWa = IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim((string) ($user->phone ?? '')));
        if ($userWa === null || $userWa !== $fromDigits) {
            Log::warning('Webhook WhatsApp: nomor WA tidak cocok dengan akun pengguna.', [
                'user_id' => $user->id,
                'expected' => $userWa,
                'from' => $fromDigits,
            ]);

            return;
        }

        app(WhatsappCrAuthorizationApplier::class)->applyDecision($dispatch, $user, $decision, $auditReference);
    }

    private function resolveDispatch(string $recipientDigits, string $contextWamId): ?WhatsappCrAuthorizationDispatch
    {
        if ($contextWamId !== '') {
            $exact = WhatsappCrAuthorizationDispatch::query()
                ->where('wam_id', $contextWamId)
                ->first();
            if ($exact !== null && $exact->recipient_wa_id === $recipientDigits) {
                return $exact;
            }
        }

        return WhatsappCrAuthorizationDispatch::query()
            ->where('recipient_wa_id', $recipientDigits)
            ->whereHas('externCr', fn ($q) => $q->whereNull('wa_authorization_decision'))
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeDecisionTitle(string $buttonTitle): ?string
    {
        $needle = mb_strtolower(trim($buttonTitle));
        if ($needle === '') {
            return null;
        }

        foreach ((array) config('services.whatsapp.cr_approve_button_titles', []) as $lbl) {
            if (mb_strtolower(trim((string) $lbl)) === $needle) {
                return ExternCr::WA_AUTH_APPROVED;
            }
        }

        foreach ((array) config('services.whatsapp.cr_reject_button_titles', []) as $lbl) {
            if (mb_strtolower(trim((string) $lbl)) === $needle) {
                return ExternCr::WA_AUTH_REJECTED;
            }
        }

        return null;
    }

    /**
     * ID pesan outbound seperti respons standar WhatsApp Cloud API: `messages[0].id` atau `data.messages[0].id`.
     *
     * Banyak proxy Mahadata mengembalikan `{"id":"msg_…"}` tanpa blok `messages` — itu tidak membuktikan penyaluran ke WhatsApp
     * dan tidak cocok sebagai `context.id` pada webhook pengguna.
     */
    public static function extractCanonicalWhatsappOutboundMessageId(ClientResponse $response): ?string
    {
        if (! $response->successful()) {
            return null;
        }

        $j = $response->json();
        if (! is_array($j)) {
            return null;
        }

        foreach (['messages.0.id', 'data.messages.0.id'] as $path) {
            $id = data_get($j, $path);
            if (is_string($id) && trim($id) !== '') {
                return trim($id);
            }
        }

        return null;
    }

    /**
     * {@see extractCanonicalWhatsappOutboundMessageId} bisa mengambil apa pun dari field `messages[0].id`.
     * Pengenal sah untuk pesan sampai akhir ke WhatsApp & dipakai `context.id` di webhook biasanya **`wamid.…`**.
     *
     * String berawalan `msg_…` sering merupakan ID internal penyedia/perantara (bukan wamid resmi konsumen webhook).
     */
    public static function isOfficialWhatsappCloudOutboundMessageId(string $messageId): bool
    {
        return trim($messageId) !== '' && str_starts_with(trim($messageId), 'wamid.');
    }

    /**
     * Menarik pengenal pesan outbound dari berbagai kemungkinan bentuk JSON (Cloud API langsung atau perantara Mahadata).
     * Untuk memastikan pesan sampai ke WhatsApp gunakan juga {@see self::extractCanonicalWhatsappOutboundMessageId}.
     */
    public static function extractOutboundWamIdFromHttpResponse(ClientResponse $response): ?string
    {
        if (! $response->successful()) {
            return null;
        }

        $j = $response->json();

        return self::extractCanonicalWhatsappOutboundMessageId($response)
            ?: data_get($j, 'result.messages.0.id')
            ?: data_get($j, 'payload.messages.0.id')
            ?: data_get($j, 'data.message.id')
            ?: data_get($j, 'message_id')
            ?: data_get($j, 'id');
    }
}
