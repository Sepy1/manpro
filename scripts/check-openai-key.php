<?php

/**
 * One-off: validasi OPENAI_API_KEY tanpa mencetak secret penuh.
 * Jalankan: php scripts/check-openai-key.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keyRaw = config('services.openai.api_key');
$trim = is_string($keyRaw) ? trim($keyRaw) : '';

echo 'Env loaded: '.($trim !== '' ? 'yes' : 'no').PHP_EOL;
echo 'Length (chars): '.strlen($trim).PHP_EOL;

if ($trim === '') {
    echo 'Result: EMPTY — set OPENAI_API_KEY di .env'.PHP_EOL;
    exit(1);
}

$masked = strlen($trim) > 11
    ? substr($trim, 0, 7).'...'.substr($trim, -4)
    : '***';

echo 'Fingerprint: '.$masked.PHP_EOL;

$response = Illuminate\Support\Facades\Http::withToken($trim)
    ->timeout(20)
    ->get('https://api.openai.com/v1/models', ['limit' => 1]);

$status = $response->status();
echo 'OpenAI /v1/models HTTP: '.$status.PHP_EOL;

if ($response->successful()) {
    echo 'Result: VALID (API menerima kunci).'.PHP_EOL;
    exit(0);
}

$json = $response->json();
$code = is_array($json) && isset($json['error']['code']) && is_string($json['error']['code'])
    ? $json['error']['code']
    : null;
$msg = is_array($json) && isset($json['error']['message']) && is_string($json['error']['message'])
    ? $json['error']['message']
    : $response->body();
$msg = strlen($msg) > 160 ? substr($msg, 0, 160).'…' : $msg;

echo 'Error code: '.($code ?? 'n/a').PHP_EOL;
echo 'Message: '.$msg.PHP_EOL;
echo 'Result: INVALID atau ditolak.'.PHP_EOL;

exit(1);
