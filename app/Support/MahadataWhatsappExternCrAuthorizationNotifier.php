<?php

namespace App\Support;

use App\Models\ExternCr;
use App\Models\User;
use App\Models\WhatsappCrAuthorizationDispatch;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim template WhatsApp Mahadata kepada pengguna dengan flag {@see User::$can_authorize_extern_cr}.
 *
 * Tombol quick reply mengirim **payload ID** bentuk APPROVE_CR_/REJECT_CR_ + {@see WhatsappCrAuthorizationDispatch::$interaction_token}
 * supaya webhook memetakan tepat satu CR satu penerima tanpa bergantung hanya pada teks "Setuju"/"Tidak".
 */
final class MahadataWhatsappExternCrAuthorizationNotifier
{
    public function notifyAuthorizersAboutNewCr(ExternCr $externCr): int
    {
        if (! config('services.mahadata_whatsapp.cr_authorization_notify_on_create', false)) {
            return 0;
        }

        return $this->notifyAuthorizersOnDemand($externCr);
    }

    /**
     * Mengirim **satu** template otorisasi CR (isinya sama produksi: 4 teks placeholder) ke nomor apa pun —
     * **tanpa** baris {@see WhatsappCrAuthorizationDispatch}, **tanpa** komponen quick reply — untuk menguji Mahadata/meta.
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

        $bodyParams = $this->crAuthorizationTemplateBodyParameters($externCr);

        $response = $this->postTemplateMessageRaw(
            $endpoint,
            $token,
            $template,
            $digits,
            $bodyParams,
            false,
            '',
            '',
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
            'whatsapp_cloud_messages_id' => ($response !== null)
                ? WhatsappCrAuthorizationWebhookProcessor::extractCanonicalWhatsappOutboundMessageId($response)
                : null,
        ]);

        return false;
    }

    /**
     * Kirim template Mahadata kepada pengguna dengan {@see User::$can_authorize_extern_cr}.
     * Tidak memeriksa flag otomatis saat pembuatan CR — dipakai juga tombol manual.
     *
     * @param  list<int>|null  $restrictToUserIds  null = semua otorisator; array non-kosong = hanya user id tersebut
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

        $includeQuickReply = (bool) config('services.mahadata_whatsapp.cr_authorization_include_quick_reply_buttons', false);

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

            if ($includeQuickReply) {
                $interactionToken = Str::lower(Str::random(32));

                $dispatch = WhatsappCrAuthorizationDispatch::query()->create([
                    'extern_cr_id' => $externCr->id,
                    'user_id' => $user->id,
                    'interaction_token' => $interactionToken,
                    'wam_id' => null,
                    'recipient_wa_id' => $digits,
                ]);

                $approvePayload = WhatsappCrAuthorizationButtonCodes::approvePayload($interactionToken);
                $rejectPayload = WhatsappCrAuthorizationButtonCodes::rejectPayload($interactionToken);

                $response = $this->postTemplateMessageRaw(
                    $endpoint,
                    $token,
                    $template,
                    $digits,
                    $bodyParams,
                    true,
                    $approvePayload,
                    $rejectPayload,
                );

                $canonicalWaId = $this->validatedCanonicalOutboundMessageId($response);
                if ($canonicalWaId !== null) {
                    $dispatch->forceFill(['wam_id' => $canonicalWaId])->save();
                    $success++;
                    Log::info('Mahadata CR auth WA: WhatsApp Cloud menyetujui penyimpanan outbound (messages[0].id).', [
                        'extern_cr_id' => $externCr->id,
                        'user_id' => $user->id,
                        'message_id_snippet' => Str::limit($canonicalWaId, 26).'…',
                        'had_quick_reply' => true,
                    ]);

                    continue;
                }

                $dispatch->delete();

                Log::notice('Mahadata CR auth WA: kirim dengan quick reply gagal atau id bukan bentuk `wamid.`; mencoba ulang tanpa tombol.', [
                    'extern_cr_id' => $externCr->id,
                    'user_id' => $user->id,
                    'mahadata_http_status' => $response?->status(),
                    'has_non_wam_messages_id' => $this->snippetNonOfficialMessageId($response),
                ]);

                $responseBodyOnly = $this->postTemplateMessageRaw(
                    $endpoint,
                    $token,
                    $template,
                    $digits,
                    $bodyParams,
                    false,
                    '',
                    '',
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
                    Log::info('Mahadata CR auth WA: fallback body-only berhasil (otomatisasi tombol webhook memakai judul/context).', [
                        'extern_cr_id' => $externCr->id,
                        'user_id' => $user->id,
                        'message_id_snippet' => Str::limit($trustedBodyId, 26).'…',
                    ]);

                    continue;
                }

                $this->logCrAuthWaNotConfirmed($response, $externCr, $user, $template.' (quick_reply)');
                $this->logCrAuthWaNotConfirmed($responseBodyOnly, $externCr, $user, $template.' (fallback body-only)');

                continue;
            }

            $response = $this->postTemplateMessageRaw(
                $endpoint,
                $token,
                $template,
                $digits,
                $bodyParams,
                false,
                '',
                '',
            );

            if ($this->validatedCanonicalOutboundMessageId($response) !== null) {
                $success++;
                Log::info('Mahadata CR auth WA: WhatsApp Cloud menyetujui penyimpanan outbound (messages[0].id).', [
                    'extern_cr_id' => $externCr->id,
                    'user_id' => $user->id,
                ]);

                continue;
            }

            $this->logCrAuthWaNotConfirmed($response, $externCr, $user, $template);
        }

        return $success;
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function crAuthorizationTemplateBodyParameters(ExternCr $externCr): array
    {
        $externCr->loadMissing('creator');

        $ttlMinutes = max(
            1,
            (int) config('services.extern_cr.signed_pdf_url_ttl_minutes', 60 * 24 * 7)
        );

        $pdfUrl = URL::temporarySignedRoute(
            'extern-cr.signed-pdf',
            now()->addMinutes($ttlMinutes),
            ['externCr' => $externCr],
            absolute: true
        );

        $deskripsi = trim((string) ($externCr->deskripsi_permintaan ?? ''));
        if ($deskripsi === '') {
            $deskripsi = trim((string) ($externCr->perubahan_diharapkan ?? ''));
        }
        if ($deskripsi === '') {
            $deskripsi = '—';
        }
        $deskripsi = Str::limit($deskripsi, 900);

        $judulCr = trim((string) ($externCr->nama ?? ''));
        if ($judulCr === '') {
            $judulCr = trim((string) ($externCr->nomor ?? ''));
        }
        if ($judulCr === '') {
            $judulCr = '—';
        }

        $pembuat = trim((string) ($externCr->creator?->name ?? ''));
        if ($pembuat === '') {
            $pembuat = 'Belum ditetapkan';
        }

        return [
            ['type' => 'text', 'text' => Str::limit($judulCr, 900)],
            ['type' => 'text', 'text' => Str::limit($pembuat, 900)],
            ['type' => 'text', 'text' => $deskripsi],
            ['type' => 'text', 'text' => Str::limit($pdfUrl, 900)],
        ];
    }

    /** Hanya untuk log (bukan penyamaran penuh nomor). */
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

        Log::notice('Mahadata CR auth WA: menerima `messages[0].id` dari perantara yang bukan `wamid.` (ACCEPT_PROXY aktif). Webhook/context.id bisa berbeda dengan Cloud API langsung — set `.env` `MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS=false` jika Anda hanya ingin menerima `wamid.`.', [
            'id_snippet' => Str::limit($id, 40).'…',
        ]);

        return $id;
    }

    /** Ringkas `messages[0].id` bila ada tetapi bukan `wamid.` (untuk log operasional). */
    private function snippetNonOfficialMessageId(?HttpClientResponse $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $raw = WhatsappCrAuthorizationWebhookProcessor::extractCanonicalWhatsappOutboundMessageId($response);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        if (WhatsappCrAuthorizationWebhookProcessor::isOfficialWhatsappCloudOutboundMessageId($raw)) {
            return null;
        }

        return Str::limit($raw, 40).'…';
    }

    /** @see https://developers.facebook.com/docs/graph-api/guides/error-handling/ */
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
        Log::warning('Mahadata CR auth WA: gagal atau tidak bisa memastikan pesan sampai WhatsApp.', $this->diagnosticsForCrAuthFailure($response, $externCr, $user, $template));
    }

    /** @return array<string, mixed> */
    private function diagnosticsForCrAuthFailure(
        ?HttpClientResponse $response,
        ExternCr $externCr,
        User $user,
        string $template,
    ): array {
        $canonical = ($response !== null)
            ? WhatsappCrAuthorizationWebhookProcessor::extractCanonicalWhatsappOutboundMessageId($response)
            : null;
        $canonicalTrimmed = is_string($canonical) ? trim($canonical) : '';
        $messagesPathLooksOfficial = $canonicalTrimmed !== ''
            && WhatsappCrAuthorizationWebhookProcessor::isOfficialWhatsappCloudOutboundMessageId($canonicalTrimmed);
        $upstreamAny = ($response !== null)
            ? WhatsappCrAuthorizationWebhookProcessor::extractOutboundWamIdFromHttpResponse($response)
            : null;
        $j = ($response !== null) ? $response->json() : null;
        $envelopeHint = null;
        if (is_array($j)) {
            foreach (['id', 'message_id'] as $k) {
                $v = $j[$k] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    $envelopeHint = Str::limit(trim($v), 52);
                    break;
                }
            }
        }

        return [
            'status' => $response?->status(),
            'body' => $response?->body(),
            'extern_cr_id' => $externCr->id,
            'user_id' => $user->id,
            'template' => $template,
            'http_considered_success' => $response !== null && $response->successful(),
            'graph_error_field_empty_or_false' => $response !== null && $this->mahadataOutboundLooksSuccessful($response),
            'whatsapp_cloud_messages_id' => ($canonicalTrimmed !== '') ? Str::limit($canonicalTrimmed, 36).'…' : null,
            'messages_path_id_is_official_wamid' => $messagesPathLooksOfficial,
            'upstream_fallback_id_hint' => ($upstreamAny !== null && trim((string) $upstreamAny) !== '' && trim((string) $upstreamAny) !== $canonicalTrimmed)
                ? Str::limit(trim((string) $upstreamAny), 36).'…'
                : null,
            'json_envelope_id_hint' => $envelopeHint,
            'first_message_hard_failure' => $response !== null && $this->whatsappFirstMessageIndicatesHardFailure($response),
            'hint' => ($canonicalTrimmed !== '' && ! $messagesPathLooksOfficial)
                ? 'Ada `messages[0].id` tetapi bukan awalan `wamid.` (mis. proxy `msg_*`). Aplikasi hanya menghitung kiriman sah bila dapat `wamid.` resmi WhatsApp Cloud. Hubungi Mahadata agar menyalurkan response Graph API apa adanya atau perbaiki ulang penyimpanan outbound.'
                : 'Jika tidak ada `messages[0].id` bernilai `wamid.…`: respons mahadata/perantara bisa belum setara Cloud API — minta contoh payload sukses resmi atau uji lagi setelah penyedia mengembalikan `wamid`.',
        ];
    }

    /**
     * HTTP 2xx: beberapa proxy mengembalikan key `error` kosong atau false walau kiriman tetap diverifikasi sukses.
     */
    private function mahadataOutboundLooksSuccessful(?HttpClientResponse $response): bool
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

        if (! array_key_exists('error', $data)) {
            return true;
        }

        $error = $data['error'];

        return $error === null
            || $error === false
            || $error === ''
            || $error === [];
    }

    /**
     * @param  list<array{type: string, text: string}>  $bodyTextParameters
     */
    private function postTemplateMessageRaw(
        string $endpoint,
        string $token,
        string $templateName,
        string $toDigits62,
        array $bodyTextParameters,
        bool $includeQuickReplyButtons,
        string $approveButtonPayloadId,
        string $rejectButtonPayloadId,
    ): ?\Illuminate\Http\Client\Response {
        $language = (string) config('services.mahadata_whatsapp.cr_authorization_template_language_code', 'id');

        $components = [
            [
                'type' => 'body',
                'parameters' => $bodyTextParameters,
            ],
        ];

        if ($includeQuickReplyButtons) {
            if (strlen($approveButtonPayloadId) > 120 || strlen($rejectButtonPayloadId) > 120) {
                Log::warning('Mahadata CR auth WA: payload tombol melewati ~128 char aman WhatsApp.', [
                    'approve_len' => strlen($approveButtonPayloadId),
                    'reject_len' => strlen($rejectButtonPayloadId),
                ]);
            }

            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => '0',
                'parameters' => [
                    ['type' => 'payload', 'payload' => $approveButtonPayloadId],
                ],
            ];
            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => '1',
                'parameters' => [
                    ['type' => 'payload', 'payload' => $rejectButtonPayloadId],
                ],
            ];
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
            Log::warning('Mahadata CR auth WA: request gagal.', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
