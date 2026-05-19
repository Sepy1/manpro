<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function fp(string $s): string
{
    $s = trim($s);

    return strlen($s) > 12 ? substr($s, 0, 8).'...'.substr($s, -4) : '(pendek)';
}

$getenv = getenv('OPENAI_API_KEY');
$envFn = env('OPENAI_API_KEY');
$config = config('services.openai.api_key');

echo 'Sumber nilai (setelah bootstrap Laravel):'.PHP_EOL;
echo 'getenv(OPENAI_API_KEY): '.(\is_string($getenv) && $getenv !== '' ? 'ada, len='.strlen($getenv).' fp='.fp($getenv) : '(kosong)').PHP_EOL;
echo 'env(\'OPENAI_API_KEY\'): '.($envFn !== null && $envFn !== '' && is_scalar($envFn) ? 'ada, len='.strlen((string) $envFn).' fp='.fp((string) $envFn) : json_encode($envFn)).PHP_EOL;
echo 'config(services.openai.api_key): '.(\is_string($config) && $config !== '' ? 'ada, len='.strlen($config).' fp='.fp($config) : '(kosong)').PHP_EOL.PHP_EOL;

if (\is_string($getenv) && $getenv !== '' && \is_string((string) $envFn) && getenv('OPENAI_API_KEY') !== false) {
    $g = getenv('OPENAI_API_KEY');
    $e = (string) $envFn;
    if ($g !== $e) {
        echo 'PERINGATAN: getenv dan env() berbeda.'.PHP_EOL;
    }
}

$path = base_path('.env');
if (is_readable($path)) {
    $content = file_get_contents($path);
    if (preg_match('/^\s*OPENAI_API_KEY\s*=\s*(.*)$/m', $content, $m)) {
        $raw = trim($m[1]);
        $raw = trim($raw, " \t\r\n\"'");
        echo 'Isi mentah di .env (baris OPENAI_API_KEY): len='.strlen($raw).' fp='.fp($raw).PHP_EOL;

        if (\is_string($config) && $config !== '' && trim($config) !== $raw) {
            echo PHP_EOL.'>>> Yang dipakai Laravel CONFIG tidak sama dengan string di file .env.'.PHP_EOL;
            echo '    Penyebab umum: variabel lingkungan Windows/Linux (OPENAI_API_KEY) sudah di-set dan menimpa .env.'.PHP_EOL;
            echo '    Cek: Panel Windows > Environment Variables, atau perintah: set OPENAI_API_KEY'.PHP_EOL;
        }
    } else {
        echo 'Tidak menemukan baris OPENAI_API_KEY di .env'.PHP_EOL;
    }
}
