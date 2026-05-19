<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiAssistantService
{
    public function __construct(
        private AssistantDataProvider $dataProvider,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{message?: string, error?: string}
     */
    public function chat(array $messages): array
    {
        $apiKeyRaw = config('services.openai.api_key');
        if (! is_string($apiKeyRaw) || trim($apiKeyRaw) === '') {
            return [
                'error' => 'OPENAI_API_KEY belum diatur. Tambahkan di file .env.',
            ];
        }

        $apiKey = $this->normalizeApiKey($apiKeyRaw);
        if ($apiKey === '') {
            return [
                'error' => 'OPENAI_API_KEY kosong setelah dibersihkan. Periksa .env (tanpa spasi atau kutipan di sekitar nilai).',
            ];
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $tools = $this->toolDefinitions();

        $messages = array_merge([
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Kamu adalah asisten Manpro untuk admin BKK Jateng.',
                    'Jawab ringkas dan jelas. Gunakan bahasa Indonesia jika pengguna memakai Indonesia.',
                    'Untuk fakta angka atau daftar terkait Data Center (PRTG), CCTV, atau proyek, WAJIB memanggil function tools — jangan mengarang data.',
                    'Setelah memakai tool, rangkum untuk pengguna (bullet atau paragraf pendek).',
                ]),
            ],
        ], $messages);

        $maxRounds = 6;

        for ($round = 0; $round < $maxRounds; $round++) {
            $headers = [
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
            ];
            $org = config('services.openai.organization');
            if (is_string($org) && trim($org) !== '') {
                $headers['OpenAI-Organization'] = trim($org);
            }

            $response = Http::withHeaders($headers)
                ->timeout(120)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                    'temperature' => 0.3,
                ]);

            if (! $response->successful()) {
                $this->logOpenAiFailure($response);

                return [
                    'error' => $this->userMessageForOpenAiFailure($response),
                ];
            }

            $json = $response->json();
            $choice = $json['choices'][0]['message'] ?? null;
            if (! is_array($choice)) {
                return ['error' => 'Respons AI tidak valid.'];
            }

            $toolCalls = $choice['tool_calls'] ?? null;
            if (is_array($toolCalls) && $toolCalls !== []) {
                $messages[] = $choice;
                foreach ($toolCalls as $toolCall) {
                    if (! is_array($toolCall)) {
                        continue;
                    }
                    $id = (string) ($toolCall['id'] ?? '');
                    $fn = $toolCall['function'] ?? null;
                    if (! is_array($fn)) {
                        continue;
                    }
                    $name = (string) ($fn['name'] ?? '');
                    $argsRaw = (string) ($fn['arguments'] ?? '{}');
                    $args = json_decode($argsRaw, true);
                    if (! is_array($args)) {
                        $args = [];
                    }
                    $result = $this->dataProvider->runTool($name, $args);
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $id,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    ];
                }

                continue;
            }

            $content = isset($choice['content']) && is_string($choice['content']) ? $choice['content'] : '';
            if ($content === '') {
                return ['error' => 'Asisten tidak mengembalikan teks.'];
            }

            return ['message' => $content];
        }

        return ['error' => 'Terlalu banyak langkah tool — coba pertanyaan yang lebih sederhana.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        $emptyObjectProps = new \stdClass;

        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_data_center_metrics',
                    'description' => 'Mengambil metrik monitoring Data Center dari PRTG: CPU, RAM, disk, dan traffic per server yang terdaftar.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'server_filter' => [
                                'type' => 'string',
                                'description' => 'Opsional: filter nama server (substring, tidak case-sensitive).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cctv_summary',
                    'description' => 'Ringkasan agregat inventaris CCTV: total perangkat, distribusi status koneksi, merk DVR terbanyak.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $emptyObjectProps,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_cctv_devices',
                    'description' => 'Mencari perangkat CCTV di database berdasarkan cabang, kantor, merk DVR, status koneksi/perangkat, atau monitor.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Kata kunci pencarian.',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maksimal baris (1–25), default 15.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_projects_snapshot',
                    'description' => 'Daftar proyek IT terbaru: nama, status, kategori, divisi, deadline, ringkasan follow-up.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'description' => 'Opsional: filter status (substring).',
                            ],
                            'name_search' => [
                                'type' => 'string',
                                'description' => 'Opsional: mencari nama proyek (substring).',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maksimal proyek (1–25), default 15.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function normalizeApiKey(string $raw): string
    {
        $key = trim($raw);
        if ((str_starts_with($key, '"') && str_ends_with($key, '"')) || (str_starts_with($key, "'") && str_ends_with($key, "'"))) {
            $key = trim(substr($key, 1, -1));
        }
        if ($key !== '' && str_starts_with(strtolower($key), 'bearer ')) {
            $key = trim(substr($key, 7));
        }

        return trim($key);
    }

    /**
     * @return array{code: ?string, message: ?string}
     */
    private function parseOpenAiErrorPayload(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return ['code' => null, 'message' => null];
        }
        $err = $json['error'] ?? null;
        if (! is_array($err)) {
            return ['code' => null, 'message' => null];
        }
        $code = $err['code'] ?? null;
        $message = $err['message'] ?? null;

        return [
            'code' => is_string($code) ? $code : null,
            'message' => is_string($message) ? $message : null,
        ];
    }

    private function logOpenAiFailure(Response $response): void
    {
        $parsed = $this->parseOpenAiErrorPayload($response);
        Log::warning('OpenAI assistant request failed', [
            'http_status' => $response->status(),
            'openai_code' => $parsed['code'],
            'openai_message' => $parsed['message'],
        ]);
    }

    private function userMessageForOpenAiFailure(Response $response): string
    {
        $status = $response->status();
        $parsed = $this->parseOpenAiErrorPayload($response);
        $code = $parsed['code'] ?? '';

        if ($status === 401 || $code === 'invalid_api_key') {
            return 'Akses ke OpenAI ditolak (HTTP 401): kunci API tidak valid atau sudah dicabut. Buat kunci baru di https://platform.openai.com/account/api-keys lalu set OPENAI_API_KEY di .env (satu baris, tanpa spasi di awal/akhir, tanpa prefiks Bearer). Setelah mengubah .env, jalankan php artisan config:clear — wajib jika Anda pernah menjalankan php artisan config:cache.';
        }

        if ($status === 429 || $code === 'rate_limit_exceeded') {
            return 'Batas permintaan atau kuota OpenAI tercapai (HTTP 429). Coba lagi nanti atau periksa usage/billing di akun OpenAI.';
        }

        if ($code === 'insufficient_quota') {
            return 'Saldo/kuota OpenAI tidak mencukupi. Periksa billing di https://platform.openai.com/account/billing';
        }

        $msg = $parsed['message'] ?? '';
        if ($msg !== '' && ! str_contains($msg, 'sk-')) {
            return 'Gagal menghubungi layanan AI (HTTP '.$status.'): '.$msg;
        }

        return 'Gagal menghubungi layanan AI (HTTP '.$status.'). Periksa storage/logs/laravel.log (entri OpenAI assistant request failed).';
    }
}
