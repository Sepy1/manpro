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
                'header_names' => array_keys($request->headers->all()),
                'event_type' => data_get($request->json()->all(), 'type'),
            ]);
            abort(403, 'Invalid signature');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $hadDeliveryStatuses = $this->logDeliveryStatuses($payload);

        $processed = 0;
        $recognized = 0;
        $inboundCount = 0;
        foreach ($this->iterateInboundMessages($payload) as ['message' => $message]) {
            $inboundCount++;
            $result = $this->processInboundMessage($message);
            if ($result === 'applied' || $result === 'already_decided') {
                $recognized++;
            }
            if ($result === 'applied') {
                $processed++;
            }
        }

        if ($inboundCount === 0 && ! $hadDeliveryStatuses) {
            Log::notice('Webhook WhatsApp: POST diterima tetapi tidak ada pesan inbound yang dikenali.', [
                'top_level_keys' => array_keys($payload),
                'event_type' => data_get($payload, 'type') ?? data_get($payload, 'event'),
                'payload_snippet' => Str::limit(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '', 800),
            ]);
        } elseif ($recognized === 0 && $inboundCount > 0) {
            Log::notice('Webhook WhatsApp: ada pesan inbound tetapi tidak diproses sebagai tombol otorisasi CR.', [
                'inbound_count' => $inboundCount,
                'event_type' => data_get($payload, 'type') ?? data_get($payload, 'event'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logDeliveryStatuses(array $payload): bool
    {
        $found = false;

        foreach (data_get($payload, 'entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach (data_get($entry, 'changes', []) as $change) {
                if (! is_array($change)) {
                    continue;
                }
                foreach (data_get($change, 'value.statuses', []) as $status) {
                    if (! is_array($status)) {
                        continue;
                    }
                    $found = true;
                    $statusType = (string) ($status['status'] ?? 'unknown');
                    $messageId = (string) ($status['id'] ?? '');
                    $errors = $status['errors'] ?? [];

                    if ($statusType === 'failed' || (is_array($errors) && $errors !== [])) {
                        $dispatch = $this->findDispatchForDeliveryStatusId($messageId);
                        Log::warning('Webhook WhatsApp: pengiriman pesan WA gagal (status webhook Meta/Mahadata).', [
                            'message_id' => $messageId !== '' ? $messageId : null,
                            'status' => $statusType,
                            'errors' => $errors,
                            'dispatch_id' => $dispatch?->id,
                            'extern_cr_id' => $dispatch?->extern_cr_id,
                        ]);
                    } else {
                        Log::info('Webhook WhatsApp: status pengiriman pesan.', [
                            'message_id' => $messageId !== '' ? $messageId : null,
                            'status' => $statusType,
                        ]);
                    }
                }
            }
        }

        return $found;
    }

    private function findDispatchForDeliveryStatusId(string $messageId): ?WhatsappCrAuthorizationDispatch
    {
        $messageId = trim($messageId);
        if ($messageId === '') {
            return null;
        }

        return WhatsappCrAuthorizationDispatch::query()
            ->where('wam_id', $messageId)
            ->orderByDesc('id')
            ->first();
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

            $eventType = strtolower(trim((string) data_get($request->json()->all(), 'type', '')));
            if (str_contains($eventType, 'inbound') || str_contains($eventType, 'received')) {
                Log::info('Webhook WhatsApp: inbound Mahadata tanpa X-Hub-Signature-256 — diterima (WHATSAPP_APP_SECRET di-set).');

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

        foreach (['body', 'event_data'] as $directKey) {
            $direct = $payload[$directKey] ?? null;
            if (is_array($direct) && $this->looksLikeInboundMessage($direct)) {
                yield ['message' => $this->normalizeProviderMessage($direct)];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function looksLikeInboundMessage(array $raw): bool
    {
        if (isset($raw['entry']) || isset($raw['messages'])) {
            return false;
        }

        return isset($raw['from']) || isset($raw['sender']) || isset($raw['button']) || isset($raw['interactive']);
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

        if (isset($message['interactive']) && is_array($message['interactive'])) {
            $interactive = &$message['interactive'];
            if (! isset($interactive['button_reply']) && isset($interactive['buttonReply']) && is_array($interactive['buttonReply'])) {
                $interactive['button_reply'] = [
                    'id' => $interactive['buttonReply']['id'] ?? '',
                    'title' => $interactive['buttonReply']['title'] ?? '',
                ];
            }
            if (($interactive['type'] ?? null) === 'buttonReply') {
                $interactive['type'] = 'button_reply';
            }
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
     * @return 'ignored'|'applied'|'already_decided'|'failed'
     */
    private function processInboundMessage(array $message): string
    {
        $button = $this->extractButtonReplyData($message);
        if ($button === null) {
            Log::info('Webhook WhatsApp: pesan inbound diabaikan (bukan tombol quick reply).', [
                'type' => $message['type'] ?? null,
                'from' => $message['from'] ?? data_get($message, 'sender.phone'),
                'keys' => array_keys($message),
                'snippet' => Str::limit(json_encode($message, JSON_UNESCAPED_UNICODE) ?: '', 500),
            ]);

            return 'ignored';
        }

        $inboundId = (string) ($message['id'] ?? '');
        if ($inboundId !== '') {
            if (! Cache::add('whatsapp:inbound_msg:'.$inboundId, 1, now()->addDays(7))) {
                return 'ignored';
            }
        }

        $btnPayloadId = $button['payload_id'];
        $title = $button['title'];

        if ($btnPayloadId === '' && $title === '') {
            return 'ignored';
        }

        $fromRaw = (string) ($message['from'] ?? data_get($message, 'sender.phone', ''));
        $fromDigits = IndonesianWhatsappPhoneNormalizer::toWaDigits62($fromRaw);
        if ($fromDigits === null) {
            Log::notice('Webhook WhatsApp: pengirim bukan nomor format yang dikenali.', ['from' => $fromRaw]);

            return 'failed';
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

                return 'failed';
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

                return 'failed';
            }
            $decision = $this->normalizeDecisionTitle($title);
            if ($decision === null) {
                Log::info('Webhook WhatsApp: judul tombol tidak dipetakan ke Setuju/Tolak.', [
                    'title' => $title,
                    'button_id' => $btnPayloadId ?: null,
                ]);

                return 'failed';
            }
        }

        $user = User::query()->find($dispatch->user_id);
        if ($user === null || ! $user->can_authorize_extern_cr) {
            Log::notice('Webhook WhatsApp: pengguna tidak berhak otorisasi.', ['dispatch_id' => $dispatch->id]);

            return 'failed';
        }

        $userWa = IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim((string) ($user->phone ?? '')));
        if ($userWa === null || $userWa !== $fromDigits) {
            Log::warning('Webhook WhatsApp: nomor WA tidak cocok dengan akun pengguna.', [
                'user_id' => $user->id,
                'expected' => $userWa,
                'from' => $fromDigits,
            ]);

            return 'failed';
        }

        $outcome = app(WhatsappCrAuthorizationApplier::class)->applyDecision($dispatch, $user, $decision, $auditReference);

        if ($outcome['result'] === WhatsappCrAuthorizationApplier::RESULT_APPLIED) {
            Log::info('Webhook WhatsApp: otorisasi CR tercatat.', [
                'extern_cr_id' => $dispatch->extern_cr_id,
                'user_id' => $user->id,
                'decision' => $decision,
            ]);

            return 'applied';
        }

        if ($outcome['result'] === WhatsappCrAuthorizationApplier::RESULT_ALREADY_DECIDED) {
            Log::info('Webhook WhatsApp: ketukan tombol diabaikan — CR sudah punya keputusan.', [
                'extern_cr_id' => $dispatch->extern_cr_id,
                'existing_decision' => $outcome['existing_decision'],
            ]);

            return 'already_decided';
        }

        Log::notice('Webhook WhatsApp: keputusan tidak diterapkan.', [
            'extern_cr_id' => $dispatch->extern_cr_id,
            'result' => $outcome['result'],
        ]);

        return 'failed';
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
        if (data_get($message, 'button.payload') !== null || data_get($message, 'button.text') !== null) {
            return [
                'payload_id' => trim((string) (data_get($message, 'button.payload') ?? data_get($message, 'button.id') ?? '')),
                'title' => trim((string) (data_get($message, 'button.text') ?? data_get($message, 'button.title') ?? '')),
            ];
        }

        $type = strtolower(trim((string) ($message['type'] ?? '')));

        if ($type === 'interactive') {
            $interactiveType = strtolower(trim((string) data_get($message, 'interactive.type', '')));
            if (in_array($interactiveType, ['button_reply', 'buttonreply'], true)) {
                return [
                    'payload_id' => trim((string) (data_get($message, 'interactive.button_reply.id') ?? data_get($message, 'interactive.buttonReply.id') ?? '')),
                    'title' => trim((string) (data_get($message, 'interactive.button_reply.title') ?? data_get($message, 'interactive.buttonReply.title') ?? '')),
                ];
            }
        }

        if ($type === 'button') {
            return [
                'payload_id' => trim((string) (data_get($message, 'button.payload') ?? data_get($message, 'button.id') ?? '')),
                'title' => trim((string) (data_get($message, 'button.text') ?? data_get($message, 'button.title') ?? '')),
            ];
        }

        if ($type === 'text') {
            $body = trim((string) (data_get($message, 'text.body') ?? data_get($message, 'body') ?? ''));
            if ($body !== '') {
                return [
                    'payload_id' => '',
                    'title' => $body,
                ];
            }
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
            $label = mb_strtolower(trim((string) $lbl));
            if ($label === '' || $label !== $needle) {
                continue;
            }

            return ExternCr::WA_AUTH_APPROVED;
        }

        if (str_starts_with($needle, 'setuju')) {
            return ExternCr::WA_AUTH_APPROVED;
        }

        foreach ((array) config('services.whatsapp.cr_reject_button_titles', []) as $lbl) {
            $label = mb_strtolower(trim((string) $lbl));
            if ($label === '' || $label !== $needle) {
                continue;
            }

            return ExternCr::WA_AUTH_REJECTED;
        }

        if (str_starts_with($needle, 'tolak') || str_starts_with($needle, 'tidak')) {
            return ExternCr::WA_AUTH_REJECTED;
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
