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

        if ($expected !== '') {
            if (! hash_equals($expected, $token)) {
                Log::notice('Webhook WhatsApp: verify token tidak cocok.', [
                    'method' => $request->method(),
                ]);

                return response('Forbidden', 403);
            }
        } elseif ($token !== '') {
            Log::warning('Webhook WhatsApp: WHATSAPP_WEBHOOK_VERIFY_TOKEN belum di .env — verifikasi diterima. Salin token dari Mahadata ke .env:', [
                'WHATSAPP_WEBHOOK_VERIFY_TOKEN' => $token,
            ]);
        } else {
            Log::warning('Webhook WhatsApp: verify token kosong di .env dan request — verifikasi diterima sementara. Isi token yang sama di Mahadata dan .env.');
        }

        $challenge = $this->hubParameter($request, 'challenge');

        Log::info('Webhook WhatsApp: verifikasi subscribe berhasil.', [
            'method' => $request->method(),
        ]);

        return response($challenge, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

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
            Log::warning('Webhook WhatsApp: signature tidak valid — payload ditolak.', [
                'has_app_secret' => trim((string) config('services.whatsapp.meta_app_secret')) !== '',
                'has_signature_header' => $request->header('X-Hub-Signature-256') !== null,
            ]);
            abort(403, 'Invalid signature');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $processed = 0;
        foreach ($this->iterateInboundMessages($payload) as ['message' => $message]) {
            if ($this->processInboundMessage($message)) {
                $processed++;
            }
        }

        if ($processed === 0) {
            Log::notice('Webhook WhatsApp: POST diterima tetapi tidak ada balasan tombol template yang diproses.', [
                'top_level_keys' => array_keys($payload),
                'payload_snippet' => Str::limit(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '', 800),
            ]);
        }
    }

    private function signatureIsValid(Request $request, string $rawBody): bool
    {
        if ((bool) config('services.whatsapp.skip_signature_validation', false)) {
            return true;
        }

        if ($this->providerVerifyTokenHeaderIsValid($request)) {
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! Str::startsWith($header, 'sha256=')) {
            $secret = trim((string) config('services.whatsapp.meta_app_secret'));
            if ($secret === '') {
                return true;
            }

            return false;
        }

        foreach ($this->signatureSecrets() as $secret) {
            $expectedMac = hash_hmac('sha256', $rawBody, $secret);
            if (hash_equals('sha256='.$expectedMac, $header)) {
                return true;
            }
        }

        return false;
    }

    private function providerVerifyTokenHeaderIsValid(Request $request): bool
    {
        $expected = trim((string) config('services.whatsapp.webhook_verify_token'));
        if ($expected === '') {
            return false;
        }

        $candidates = [
            $request->header('X-Webhook-Token'),
            $request->header('X-Verify-Token'),
            $request->header('X-Mahadata-Webhook-Token'),
            $request->header('X-Hub-Verify-Token'),
        ];

        $auth = (string) $request->header('Authorization', '');
        if (Str::startsWith($auth, 'Bearer ')) {
            $candidates[] = trim(substr($auth, 7));
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && hash_equals($expected, trim($candidate))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function signatureSecrets(): array
    {
        $secrets = [];
        foreach ([
            config('services.whatsapp.meta_app_secret'),
            config('services.whatsapp.webhook_verify_token'),
        ] as $candidate) {
            $secret = trim((string) ($candidate ?? ''));
            if ($secret !== '') {
                $secrets[] = $secret;
            }
        }

        return array_values(array_unique($secrets));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Generator<int, array{message: array<string, mixed>}>
     */
    private function iterateInboundMessages(array $payload): \Generator
    {
        $payload = $this->normalizeWebhookPayload($payload);

        foreach ($payload['entry'] ?? [] as $entry) {
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
                    if (is_array($message)) {
                        yield ['message' => $this->normalizeProviderMessage($message)];
                    }
                }
            }
        }

        foreach ($payload['messages'] ?? [] as $message) {
            if (is_array($message)) {
                yield ['message' => $this->normalizeProviderMessage($message)];
            }
        }

        $nestedMessages = data_get($payload, 'value.messages');
        if (is_array($nestedMessages)) {
            foreach ($nestedMessages as $message) {
                if (is_array($message)) {
                    yield ['message' => $this->normalizeProviderMessage($message)];
                }
            }
        }

        foreach ([
            'whatsappInboundMessage',
            'whatsapp_inbound_message',
            'inboundMessage',
            'inbound_message',
        ] as $wrapperKey) {
            foreach ([$payload, is_array($payload['data'] ?? null) ? $payload['data'] : []] as $container) {
                if ($container === []) {
                    continue;
                }
                $wrapped = $container[$wrapperKey] ?? null;
                if (is_array($wrapped)) {
                    yield ['message' => $this->normalizeProviderMessage($wrapped)];
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeProviderMessage(array $raw): array
    {
        $message = $raw;

        if (! isset($message['from']) && isset($message['sender']) && is_array($message['sender'])) {
            $message['from'] = $message['sender']['phone'] ?? $message['sender']['wa_id'] ?? $message['sender']['id'] ?? null;
        }

        if (! isset($message['id']) && isset($message['wamid'])) {
            $message['id'] = $message['wamid'];
        }

        if (isset($message['context']) && is_array($message['context'])) {
            $message['context']['id'] ??= $message['context']['wamid'] ?? $message['context']['message_id'] ?? null;
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeWebhookPayload(array $payload): array
    {
        foreach (['payload', 'data', 'body', 'event_data', 'message'] as $wrap) {
            $inner = $payload[$wrap] ?? null;
            if (! is_array($inner)) {
                continue;
            }
            if (isset($inner['entry']) || isset($inner['messages']) || isset($inner['value'])) {
                return $inner;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function processInboundMessage(array $message): bool
    {
        $button = $this->extractButtonReplyData($message);
        if ($button === null) {
            return false;
        }

        $inboundId = (string) ($message['id'] ?? '');
        if ($inboundId !== '') {
            if (! Cache::add('whatsapp:inbound_msg:'.$inboundId, 1, now()->addDays(7))) {
                return false;
            }
        }

        $btnPayloadId = $button['payload_id'];
        $title = $button['title'];

        if ($btnPayloadId === '' && $title === '') {
            return false;
        }

        $fromRaw = (string) ($message['from'] ?? data_get($message, 'sender.phone', ''));
        $fromDigits = IndonesianWhatsappPhoneNormalizer::toWaDigits62($fromRaw);
        if ($fromDigits === null) {
            Log::notice('Webhook WhatsApp: pengirim bukan nomor format yang dikenali.', ['from' => $fromRaw]);

            return false;
        }

        $contextWamId = (string) (
            data_get($message, 'context.id')
            ?? data_get($message, 'context.wamid')
            ?? data_get($message, 'context.message_id')
            ?? ''
        );

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

            if ($dispatch === null) {
                $dispatch = WhatsappCrAuthorizationDispatch::query()
                    ->where('interaction_token', $parsedPayload['interaction_token'])
                    ->orderByDesc('id')
                    ->first();
            }

            $decision = $parsedPayload['decision'];

            if ($dispatch === null) {
                Log::notice('Webhook WhatsApp: payload APPROVE_CR_/REJECT_CR_ tidak cocok dispatch.', [
                    'interaction_token_snip' => Str::limit((string) ($parsedPayload['interaction_token'] ?? ''), 12, '…'),
                    'from' => $fromDigits,
                ]);

                return false;
            }
        } else {
            $dispatch = $this->resolveDispatch($fromDigits, $contextWamId);
            if ($dispatch === null) {
                Log::notice('Webhook WhatsApp: tidak menemukan dispatch CR untuk tombol.', [
                    'from' => $fromDigits,
                    'context_wam_id' => $contextWamId ?: null,
                    'button_id' => $btnPayloadId ?: null,
                    'button_title' => $title ?: null,
                    'message_type' => $message['type'] ?? null,
                ]);

                return false;
            }
            $decision = $this->normalizeDecisionTitle($title);
            if ($decision === null) {
                Log::info('Webhook WhatsApp: judul tombol tidak dipetakan ke Setuju/Tolak.', [
                    'title' => $title,
                    'button_id' => $btnPayloadId ?: null,
                ]);

                return false;
            }
        }

        $user = User::query()->find($dispatch->user_id);
        if ($user === null || ! $user->can_authorize_extern_cr) {
            Log::notice('Webhook WhatsApp: pengguna tidak berhak otorisasi.', ['dispatch_id' => $dispatch->id]);

            return false;
        }

        $userWa = IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim((string) ($user->phone ?? '')));
        if ($userWa === null || $userWa !== $fromDigits) {
            Log::warning('Webhook WhatsApp: nomor WA tidak cocok dengan akun pengguna.', [
                'user_id' => $user->id,
                'expected' => $userWa,
                'from' => $fromDigits,
            ]);

            return false;
        }

        $outcome = app(WhatsappCrAuthorizationApplier::class)->applyDecision($dispatch, $user, $decision, $auditReference);

        if ($outcome['result'] === WhatsappCrAuthorizationApplier::RESULT_APPLIED) {
            Log::info('Webhook WhatsApp: otorisasi CR tercatat.', [
                'extern_cr_id' => $dispatch->extern_cr_id,
                'user_id' => $user->id,
                'decision' => $decision,
            ]);

            return true;
        }

        Log::notice('Webhook WhatsApp: keputusan tidak diterapkan (mungkin sudah ada sebelumnya).', [
            'extern_cr_id' => $dispatch->extern_cr_id,
            'result' => $outcome['result'],
        ]);

        return false;
    }

    /**
     * Template quick reply → type `button` + button.payload.
     * Interactive session → type `interactive` + interactive.button_reply.
     *
     * @param  array<string, mixed>  $message
     * @return array{payload_id: string, title: string}|null
     */
    private function extractButtonReplyData(array $message): ?array
    {
        $type = strtolower(trim((string) ($message['type'] ?? '')));

        if ($type === 'interactive' && data_get($message, 'interactive.type') === 'button_reply') {
            return [
                'payload_id' => trim((string) data_get($message, 'interactive.button_reply.id', '')),
                'title' => trim((string) data_get($message, 'interactive.button_reply.title', '')),
            ];
        }

        if ($type === 'button') {
            return [
                'payload_id' => trim((string) (data_get($message, 'button.payload') ?? data_get($message, 'button.id') ?? '')),
                'title' => trim((string) (data_get($message, 'button.text') ?? data_get($message, 'button.title') ?? '')),
            ];
        }

        return null;
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

            $exact = WhatsappCrAuthorizationDispatch::query()
                ->where('recipient_wa_id', $recipientDigits)
                ->where('wam_id', $contextWamId)
                ->first();
            if ($exact !== null) {
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

    public static function isOfficialWhatsappCloudOutboundMessageId(string $messageId): bool
    {
        return trim($messageId) !== '' && str_starts_with(trim($messageId), 'wamid.');
    }

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
