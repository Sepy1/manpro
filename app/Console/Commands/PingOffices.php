<?php

namespace App\Console\Commands;

use App\Models\Kantor;
use App\Models\KasKantor;
use App\Models\NetworkMonitorSetting;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class PingOffices extends Command
{
    protected $signature = 'network:ping-offices {--force : Abaikan interval dan ping semua IP sekarang}';
    protected $description = 'Menguji koneksi wireless kantor dengan ICMP ping dan menyimpan status terakhir.';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(NetworkMonitorSetting::intervalMinutes());
        $total = 0;

        foreach ([Kantor::class, KasKantor::class] as $modelClass) {
            $query = $modelClass::query()
                ->where('is_active', true)
                ->whereNotNull('wireless_ip')
                ->where('wireless_ip', '<>', '');

            if (! $this->option('force')) {
                $query->where(fn ($q) => $q->whereNull('ping_checked_at')->orWhere('ping_checked_at', '<=', $cutoff));
            }

            $query->orderBy('id')->chunkById(100, function ($offices) use (&$total) {
                foreach ($offices as $office) {
                    [$status, $latency] = $this->ping((string) $office->wireless_ip);
                    $office->forceFill([
                        'ping_status' => $status,
                        'ping_latency_ms' => $latency,
                        'ping_checked_at' => now(),
                    ])->save();
                    $total++;
                }
            });
        }

        $this->info("Ping selesai: {$total} kantor diperiksa.");

        return self::SUCCESS;
    }

    private function ping(string $ip): array
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['rto', null];
        }

        $command = PHP_OS_FAMILY === 'Windows'
            ? ['ping', '-n', '1', '-w', '2000', $ip]
            : ['ping', '-c', '1', '-W', '2', $ip];
        $startedAt = hrtime(true);
        $process = new Process($command);
        $process->setTimeout(5);

        try {
            $process->run();
            $elapsed = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            return $process->isSuccessful() ? ['reply', $elapsed] : ['rto', null];
        } catch (\Throwable) {
            return ['rto', null];
        }
    }
}
