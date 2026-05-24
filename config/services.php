<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    /*
     * OTP admin 2FA via Mahadata WhatsApp API (Bearer).
     */
    'mahadata_whatsapp' => [
        /*
         * Prioritas nama variabel utama; dukung alias salah ketik yang sering dipakai.
         */
        'endpoint' => (static function (): string {
            foreach (
                [
                    env('MAHADATA_WHATSAPP_MESSAGE_ENDPOINT'),
                    env('MAHADATA_WHATSAPP_ENDPOINT'),
                    env('MAHADATA_WHATSAPP_URL'),
                    env('MAHADATA_WHATSAPP_API_URL'),
                ] as $candidate
            ) {
                $t = trim((string) ($candidate ?? ''));
                if ($t !== '') {
                    return $t;
                }
            }

            return '';
        })(),
        /*
         * Laravel mengirim `Authorization: Bearer <token>`; hapus prefiks Bearer bila tertulis di .env
         * (menghindari header `Bearer Bearer …` yang sering menghasilkan 401).
         */
        'token' => preg_replace(
            '#^Bearer\s+#i',
            '',
            trim((string) env('MAHADATA_WHATSAPP_TOKEN', ''))
        ) ?? '',
        'template_name' => env('MAHADATA_WHATSAPP_TEMPLATE_NAME', 'otp_branchless'),
        'template_language_code' => env('MAHADATA_WHATSAPP_TEMPLATE_LANGUAGE_CODE', 'id'),
        'timeout_seconds' => (int) env('MAHADATA_WHATSAPP_HTTP_TIMEOUT', 30),
        /*
         | OTP 2FA: auto = kirim body + tombol URL dulu; bila gagal, kirim lagi hanya body (template banyak yang tanpa tombol).
         | full = selalu body + tombol URL (template harus punya URL button index 0).
         | body_only = hanya body satu placeholder teks (= kode OTP).
         */
        'otp_send_mode' => strtolower((string) env('MAHADATA_WHATSAPP_OTP_SEND_MODE', 'auto')),
        /*
         | Notifikasi otorisasi CR eksternal (Mahadata sama dengan OTP: endpoint & token bersama).
         | Template nama `change_request_manpro` atau setara harus mencocokkan jumlah placeholder body + struktur tombol di Meta Business.
         */
        'cr_authorization_template_name' => env(
            'MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_NAME',
            'notif_cr_manpro'
        ),
        'cr_authorization_template_language_code' => env('MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE', 'id'),
        'cr_authorization_single_url_button' => filter_var(
            env('MAHADATA_WHATSAPP_CR_AUTH_SINGLE_URL_BUTTON', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cr_authorization_include_quick_reply_buttons' => filter_var(
            env('MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cr_authorization_include_url_buttons' => filter_var(
            env('MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_URL_BUTTONS', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cr_authorization_notify_on_create' => filter_var(
            env('MAHADATA_WHATSAPP_CR_AUTH_NOTIFY_ON_CREATE', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        /*
         | Mahadata/perantara sering mengembalikan `messages[0].id` berbentuk `msg_…` bukan `wamid.` resmi WhatsApp Cloud.
         | Default true — agar tombol «Kirim otorisator» tidak dianggap gagal walau penyedia pakai bentuk itu (set false jika Anda hanya percaya `wamid.`).
         */
        'cr_authorization_accept_proxy_message_ids' => filter_var(
            env('MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cr_authorization_confirmation_template_name' => env(
            'MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_TEMPLATE_NAME',
            'konfirmasi_otorisasi_manpro'
        ),
        'cr_authorization_confirmation_template_language_code' => env(
            'MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_TEMPLATE_LANGUAGE_CODE',
            env('MAHADATA_WHATSAPP_CR_AUTH_TEMPLATE_LANGUAGE_CODE', 'id')
        ),
        'cr_authorization_confirmation_enabled' => filter_var(
            env('MAHADATA_WHATSAPP_CR_AUTH_CONFIRMATION_ENABLED', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /*
     | Webhook callback WhatsApp Cloud API — pemrosesan inbound tombol quick reply otorisasi CR.
     | Endpoint: GET|POST {APP_URL}/webhook/whatsapp
     */
    'whatsapp' => [
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'meta_app_secret' => env('WHATSAPP_APP_SECRET'),
        'skip_signature_validation' => filter_var(env('WHATSAPP_WEBHOOK_SKIP_SIGNATURE_VALIDATE', false), FILTER_VALIDATE_BOOLEAN),
        'cr_approve_button_titles' => array_values(array_filter(array_map('trim', explode(',', (string) env('WHATSAPP_CR_APPROVE_LABELS', 'Setuju,Setujui'))))),
        'cr_reject_button_titles' => array_values(array_filter(array_map('trim', explode(',', (string) env('WHATSAPP_CR_REJECT_LABELS', 'Tidak,Tolak'))))),
    ],

    'extern_cr' => [
        'signed_pdf_url_ttl_minutes' => max(60, (int) env('EXTERN_CR_SIGNED_PDF_URL_TTL_MINUTES', 10080)),
    ],

    'prtg' => [
        'base_url' => env('PRTG_BASE_URL'),
        'username' => env('PRTG_USERNAME'),
        'passhash' => env('PRTG_PASSHASH'),
        'verify_ssl' => env('PRTG_VERIFY_SSL', false),
    ],

];
