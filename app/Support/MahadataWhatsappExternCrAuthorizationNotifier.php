<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\User;
use App\Models\WhatsappCrAuthorizationDispatch;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim template WhatsApp Mahadata kepada pengguna dengan flag {@see User::$can_authorize_extern_cr}.
 *
 * Default: tombol **quick reply** dengan payload APPROVE_CR_/REJECT_CR_ + token; keputusan diproses webhook.
 * Opsional: tombol URL ({@see MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS}).
 */
final class MahadataWhatsappExternCrAuthorizationNotifier
{
    private const BUTTON_NONE = 'none';

    private const BUTTON_QUICK_REPLY = 'quick_reply';

    private const BUTTON_URL = 'url';

    public function notifyAuthorizersAboutNewCr(ExternCr $externCr): int
    {
        if (! config('services.mahadata_whatsapp.cr_authorization_notify_on_create', false)) {
            return 0;
        }

        return $this->notifyAuthorizersOnDemand($externCr);
    }

    /**
     * Mengirim **satu** template otorisasi CR (4 placeholder body) tanpa dispatch / tombol — uji Mahadata.
     */
    public function sendTestCrAuthorizationTemplate(ExternCr $externCr, string $waRecipientInput): bool
    {
        $endpoint = trim((string) config('services.mahadata_whatsapp.endpoint'));
        $token = trim((string) config('services.mahadata_whatsapp.token'));
        $template = trim((string) config('services.mahadata_whatsapp.cr_authorization_template_name'));

        if ($endpoint === '' || $token === '' || $template === '') {
            Log::warning('Mahadata CR auth WA (uji): endpoint/token/template CR kosong.');

            return false;
        }

        $digits = IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim($waRecipientInput));
        if ($digits === null) {
            Log::warning('Mahadata CR auth WA (uji): nomor penerima tidak valid.', ['input' => $waRecipientInput]);

            return false;
        }

        $response = $this->postTemplateMessageRaw(
            $endpoint,
            $token,
            $template,
            $digits,
            $this->crAuthorizationTemplateBodyParameters($externCr),
            self::BUTTON_NONE,
            '',
            '',
            [],
        );

        $canonical = $this->validatedCanonicalOutboundMessageId($response);
        if ($canonical !== null) {
            Log::info('Mahadata CR auth WA (uji): penyaluran dikonfirmasi Cloud API.', [
                'extern_cr_id' => $externCr->id,
                'to_masked' => $this->maskedDigitsForDebug($digits),
                'message_id_snippet' => Str::limit($canonical, 26).'…',
            ]);

            return true;
        }

        Log::warning('Mahadata CR auth WA (uji): tidak dapat memastikan pesan sampai WhatsApp Cloud.', [
            'extern_cr_id' => $externCr->id,
            'to_masked' => $this->maskedDigitsForDebug($digits),
            'status' => $response?->status(),
            'body' => $response?->body(),
        ]);

        return false;
    }

    /**
     * @param  list<int>|null  $restrictToUserIds
     */
    public function notifyAuthorizersOnDemand(ExternCr $externCr, ?array $restrictToUserIds = null): int
    {
        $endpoint = trim((string) config('services.mahadata_whatsapp.endpoint'));
        $token = trim((string) config('services.mahadata_whatsapp.token'));
        $template = trim((string) config('services.mahadata_whatsapp.cr_authorization_template_name'));

        if ($endpoint === '' || $token === '' || $template === '') {
            return 0;
        }

        $bodyParams = $this->crAuthorizationTemplateBodyParameters($externCr);
        $this->logMetaParameterIssues($bodyParams, $externCr);
        $buttonMode = $this->resolvedButtonMode();

        $users = User::query()
            ->where('can_authorize_extern_cr', true)
            ->whereIn('role', User::ROLES)
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'name']);

        if ($restrictToUserIds !== null) {
            $allow = array_flip(array_values(array_unique(array_map('intval', $restrictToUserIds))));
            $users = $users->filter(static fn (User $u) => isset($allow[(int) $u->id]))->values();
        }

        $success = 0;
        foreach ($users as $user) {
            $digits = IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim((string) $user->phone));
            if ($digits === null) {
                Log::warning('Mahadata CR auth WA: nomor pengguna tidak valid, dilewati.', ['user_id' => $user->id]);

                continue;
            }

            if ($buttonMode === self::BUTTON_NONE) {
                $response = $this->postTemplateMessageRaw($endpoint, $token, $template, $digits, $bodyParams, self::BUTTON_NONE, '', '', []);
                if ($this->validatedCanonicalOutboundMessageId($response) !== null) {
                    $success++;
                } else {
                    $this->logCrAuthWaNotConfirmed($response, $externCr, $user, $template);
                }

                continue;
            }

            $interactionToken = Str::lower(Str::random(32));

            $dispatch = WhatsappCrAuthorizationDispatch::query()->create([
                'extern_cr_id' => $externCr->id,
                'user_id' => $user->id,
                'interaction_token' => $interactionToken,
                'wam_id' => null,
                'recipient_wa_id' => $digits,
            ]);

            [$approveValue, $rejectValue] = $this->quickReplyButtonValues($interactionToken);
            $urlButtonParameters = $this->urlButtonParameters($externCr, $interactionToken);

            $response = $this->postTemplateMessageRaw(
                $endpoint,
                $token,
                $template,
                $digits,
                $bodyParams,
                $buttonMode,
                $approveValue,
                $rejectValue,
                $urlButtonParameters,
            );

            $canonicalWaId = $this->validatedCanonicalOutboundMessageId($response);
            if ($canonicalWaId !== null) {
                $dispatch->forceFill(['wam_id' => $canonicalWaId])->save();
                $success++;
                Log::info('Mahadata CR auth WA: outbound dikonfirmasi.', [
                    'extern_cr_id' => $externCr->id,
                    'user_id' => $user->id,
                    'dispatch_id' => $dispatch->id,
                    'message_id_snippet' => Str::limit($canonicalWaId, 26).'…',
                    'button_mode' => $buttonMode,
                    'approval_url' => $buttonMode === self::BUTTON_URL
                        ? WhatsappCrAuthorizationButtonCodes::approvalLandingFullUrl($interactionToken)
                        : null,
                    'note' => WhatsappCrAuthorizationWebhookProcessor::isOfficialWhatsappCloudOutboundMessageId($canonicalWaId)
                        ? 'wamid resmi'
                        : 'id proxy Mahadata — tunggu webhook status delivered/failed',
                ]);

                continue;
            }

            $dispatch->delete();

            Log::notice('Mahadata CR auth WA: kirim dengan tombol gagal; mencoba ulang tanpa tombol.', [
                'extern_cr_id' => $externCr->id,
                'user_id' => $user->id,
                'button_mode' => $buttonMode,
                'mahadata_http_status' => $response?->status(),
            ]);

            $responseBodyOnly = $this->postTemplateMessageRaw(
                $endpoint,
                $token,
                $template,
                $digits,
                $bodyParams,
                self::BUTTON_NONE,
                '',
                '',
                [],
            );

            $trustedBodyId = $this->validatedCanonicalOutboundMessageId($responseBodyOnly);
            if ($trustedBodyId !== null) {
                WhatsappCrAuthorizationDispatch::query()->create([
                    'extern_cr_id' => $externCr->id,
                    'user_id' => $user->id,
                    'interaction_token' => null,
                    'wam_id' => $trustedBodyId,
                    'recipient_wa_id' => $digits,
                ]);
                $success++;
                Log::info('Mahadata CR auth WA: fallback body-only berhasil (webhook memakai judul/context).', [
                    'extern_cr_id' => $externCr->id,
                    'user_id' => $user->id,
                ]);

                continue;
            }

            $this->logCrAuthWaNotConfirmed($response, $externCr, $user, $template.' ('.$buttonMode.')');
            $this->logCrAuthWaNotConfirmed($responseBodyOnly, $externCr, $user, $template.' (fallback body-only)');
        }

        return $success;
    }

    private function resolvedButtonMode(): string
    {
        if ((bool) config('services.mahadata_whatsapp.cr_authorization_include_quick_reply_buttons', true)) {
            return self::BUTTON_QUICK_REPLY;
        }

        if ((bool) config('services.mahadata_whatsapp.cr_authorization_include_url_buttons', false)) {
            return self::BUTTON_URL;
        }

        return self::BUTTON_NONE;
    }

    /** @return array{0: string, 1: string} */
    private function quickReplyButtonValues(string $interactionToken): array
    {
        return [
            WhatsappCrAuthorizationButtonCodes::approvePayload($interactionToken),
            WhatsappCrAuthorizationButtonCodes::rejectPayload($interactionToken),
        ];
    }

    /**
     * Suffix dinamis untuk tombol URL template Meta (`konfirmasi_cr_manpro` atau mode legacy).
     *
     * @return list<string>
     */
    private function urlButtonParameters(ExternCr $externCr, string $interactionToken): array
    {
        if ((bool) config('services.mahadata_whatsapp.cr_authorization_single_url_button', false)) {
            return [
                WhatsappTemplateTextSanitizer::urlParameter(
                    WhatsappCrAuthorizationButtonCodes::approvalLandingUrlSuffix($interactionToken)
                ),
            ];
        }

        return [
            WhatsappTemplateTextSanitizer::urlParameter(
                WhatsappCrAuthorizationButtonCodes::approvalLandingUrlSuffix($interactionToken)
            ),
            WhatsappTemplateTextSanitizer::urlParameter(
                WhatsappCrAuthorizationButtonCodes::viewCrUrlSuffix($externCr)
            ),
        ];
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function crAuthorizationTemplateBodyParameters(ExternCr $externCr): array
    {
        $externCr->loadMissing('creator');

        $daftarPerubahan = trim((string) ($externCr->perubahan_diharapkan ?? ''));
        if ($daftarPerubahan === '') {
            $daftarPerubahan = trim((string) ($externCr->deskripsi_permintaan ?? ''));
        }
        if ($daftarPerubahan === '') {
            $daftarPerubahan = '—';
        }

        $judulCr = trim((string) ($externCr->nama ?? ''));
        if ($judulCr === '') {
            $judulCr = trim((string) ($externCr->nomor ?? ''));
        }
        if ($judulCr === '') {
            $judulCr = '—';
        }

        $nomorCr = trim((string) ($externCr->nomor ?? ''));
        if ($nomorCr === '') {
            $nomorCr = $judulCr;
        }

        $pembuat = trim((string) ($externCr->creator?->name ?? ''));
        if ($pembuat === '') {
            $pembuat = 'Belum ditetapkan';
        }

        return [
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($nomorCr)],
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($judulCr)],
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($pembuat)],
            ['type' => 'text', 'text' => WhatsappTemplateTextSanitizer::bodyParameter($daftarPerubahan)],
        ];
    }

    /**
     * @param  list<array{type: string, text: string}>  $bodyParams
     */
    private function logMetaParameterIssues(array $bodyParams, ExternCr $externCr): void
    {
        foreach ($bodyParams as $index => $param) {
            $text = (string) ($param['text'] ?? '');
            $reason = WhatsappTemplateTextSanitizer::metaRejectReason($text);
            if ($reason === null) {
                continue;
            }

            Log::warning('Mahadata CR auth WA: parameter template masih ditolak Meta (#132018).', [
                'extern_cr_id' => $externCr->id,
                'placeholder_index' => $index + 1,
                'reason' => $reason,
                'text_snippet' => Str::limit($text, 120),
            ]);
        }
    }

    private function maskedDigitsForDebug(string $digits62): string
    {
        $d = preg_replace('/\D+/', '', $digits62) ?? '';
        $len = strlen($d);

        return match (true) {
            $len < 8 => '—',
            default => substr($d, 0, 4).str_repeat('•', max(2, $len - 7)).substr($d, -3),
        };
    }

    /** @return non-empty-string|null */
    private function validatedCanonicalOutboundMessageId(?HttpClientResponse $response): ?string
    {
        if ($response === null || ! $this->mahadataOutboundLooksSuccessful($response)) {
            return null;
        }

        if ($this->whatsappFirstMessageIndicatesHardFailure($response)) {
            return null;
        }

        $id = WhatsappCrAuthorizationWebhookProcessor::extractCanonicalWhatsappOutboundMessageId($response);
        if ($id === null || trim($id) === '') {
            return null;
        }

        $id = trim($id);

        if (WhatsappCrAuthorizationWebhookProcessor::isOfficialWhatsappCloudOutboundMessageId($id)) {
            return $id;
        }

        if (! (bool) config('services.mahadata_whatsapp.cr_authorization_accept_proxy_message_ids', false)) {
            return null;
        }

        return $id;
    }

    private function whatsappFirstMessageIndicatesHardFailure(HttpClientResponse $response): bool
    {
        $j = $response->json();
        if (! is_array($j)) {
            return false;
        }

        foreach (['messages', 'data.messages'] as $path) {
            $row = data_get($j, $path.'.0');
            if (! is_array($row)) {
                continue;
            }

            $status = strtolower(trim((string) ($row['message_status'] ?? '')));
            if (in_array($status, ['failed', 'rejected', 'deleted'], true)) {
                return true;
            }

            $errs = $row['errors'] ?? null;
            if (is_array($errs) && $errs !== []) {
                return true;
            }
        }

        return false;
    }

    private function logCrAuthWaNotConfirmed(
        ?HttpClientResponse $response,
        ExternCr $externCr,
        User $user,
        string $template,
    ): void {
        Log::warning('Mahadata CR auth WA: gagal atau tidak bisa memastikan pesan sampai WhatsApp.', [
            'status' => $response?->status(),
            'body' => $response?->body(),
            'extern_cr_id' => $externCr->id,
            'user_id' => $user->id,
            'template' => $template,
        ]);
    }

    private function mahadataOutboundLooksSuccessful(?HttpClientResponse $response): bool
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

    /**
     * @param  list<array{type: string, text: string}>  $bodyTextParameters
     * @param  list<string>  $urlButtonParameters
     */
    private function postTemplateMessageRaw(
        string $endpoint,
        string $token,
        string $templateName,
        string $toDigits62,
        array $bodyTextParameters,
        string $buttonMode,
        string $approveButtonValue,
        string $rejectButtonValue,
        array $urlButtonParameters = [],
    ): ?HttpClientResponse {
        $language = (string) config('services.mahadata_whatsapp.cr_authorization_template_language_code', 'id');

        $components = [
            [
                'type' => 'body',
                'parameters' => $bodyTextParameters,
            ],
        ];

        if ($buttonMode === self::BUTTON_QUICK_REPLY) {
            if (strlen($approveButtonValue) > 120 || strlen($rejectButtonValue) > 120) {
                Log::warning('Mahadata CR auth WA: payload tombol melewati ~128 char aman WhatsApp.', [
                    'approve_len' => strlen($approveButtonValue),
                    'reject_len' => strlen($rejectButtonValue),
                ]);
            }

            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => '0',
                'parameters' => [
                    ['type' => 'payload', 'payload' => $approveButtonValue],
                ],
            ];
            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => '1',
                'parameters' => [
                    ['type' => 'payload', 'payload' => $rejectButtonValue],
                ],
            ];
        } elseif ($buttonMode === self::BUTTON_URL) {
            foreach (array_values($urlButtonParameters) as $index => $urlParameter) {
                if ($urlParameter === '') {
                    continue;
                }
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => (string) $index,
                    'parameters' => [
                        ['type' => 'text', 'text' => $urlParameter],
                    ],
                ];
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toDigits62,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
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
            Log::warning('Mahadata CR auth WA: request gagal.', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
