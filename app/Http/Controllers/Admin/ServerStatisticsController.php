<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DcDrcDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

class ServerStatisticsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $devices = $this->getDevicesForStatistics();

        return view('pages.dashboard.aset-ti.server-statistics', [
            'rows' => $devices->map(function (DcDrcDevice $device): array {
                return [
                    'id' => $device->id,
                    'server' => $device->server_name ?? '-',
                    'availability' => [
                        'ping' => trim((string) $device->objid_ping) !== '',
                        'cpu' => trim((string) $device->objid_cpu) !== '',
                        'ram' => trim((string) $device->objid_ram) !== '',
                        'traffic' => trim((string) $device->objid_traffic) !== '',
                        'disk' => trim((string) $device->objid_diskfree) !== '',
                    ],
                ];
            })->values(),
            'sensorUrlTemplate' => route('admin.aset-ti.server-statistics.sensor', ['device' => '__DEVICE__', 'metric' => '__METRIC__']),
            'pdfUrl' => route('admin.aset-ti.server-statistics.pdf'),
        ]);
    }

    public function sensor(DcDrcDevice $device, string $metric, Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        if (! in_array($metric, ['ping', 'cpu', 'ram', 'traffic', 'disk'], true)) {
            return response()->json([
                'ok' => false,
                'metric' => $metric,
                'message' => 'Metric sensor tidak valid.',
            ], 422);
        }
        $objid = $this->resolveObjidByMetric($device, $metric);
        if ($objid === '') {
            return response()->json([
                'ok' => true,
                'metric' => $metric,
                'available' => false,
            ]);
        }

        [$startDate, $endDate, $validationError] = $this->resolvePeriodFromRequest($request);

        if ($validationError !== null) {
            return response()->json([
                'ok' => false,
                'metric' => $metric,
                'available' => true,
                'message' => $validationError,
            ], 422);
        }

        try {
            $payload = $this->getMetricPayload(
                $metric,
                $objid,
                $startDate,
                $endDate
            );

            return response()->json($payload);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch metric sensor data.', [
                'device_id' => $device->id,
                'metric' => $metric,
                'objid' => $objid,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'metric' => $metric,
                'available' => true,
                'message' => 'Sensor gagal diambil dari API PRTG.',
            ], 502);
        }
    }

    public function pdf(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        [$startDate, $endDate, $validationError] = $this->resolvePeriodFromRequest($request);
        if ($validationError !== null) {
            return redirect()
                ->route('admin.aset-ti.server-statistics.index')
                ->with('flash_error', $validationError);
        }

        $servers = [];
        $devices = $this->getDevicesForStatistics();

        foreach ($devices as $device) {
            $sensorRows = [];

            foreach (['cpu', 'ram', 'traffic', 'disk'] as $metric) {
                $objid = $this->resolveObjidByMetric($device, $metric);
                if ($objid === '') {
                    continue;
                }

                try {
                    $payload = $this->getMetricPayload($metric, $objid, $startDate, $endDate);
                } catch (Throwable $exception) {
                    Log::warning('Failed to build server statistics PDF metric payload.', [
                        'device_id' => $device->id,
                        'metric' => $metric,
                        'objid' => $objid,
                        'message' => $exception->getMessage(),
                    ]);
                    continue;
                }

                $trend = $payload['trend'] ?? ['labels' => [], 'min' => [], 'max' => [], 'avg' => []];
                if (empty($trend['labels'])) {
                    continue;
                }

                $sensorRows[] = [
                    'key' => $metric,
                    'label' => $this->metricLabelForPdf($metric),
                    'unit' => $this->metricUnitForPdf($metric),
                    'stats' => $payload['stats'] ?? ['min' => null, 'max' => null, 'avg' => null],
                    'trend' => $trend,
                ];
            }

            if (! empty($sensorRows)) {
                $servers[] = [
                    'server' => $device->server_name ?: '-',
                    'sensors' => $sensorRows,
                ];
            }
        }

        $pdf = Pdf::loadView('pages.dashboard.aset-ti.server-statistics-pdf', [
            'servers' => $servers,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('laporan-statistik-server-'.$startDate->format('Ymd').'-'.$endDate->format('Ymd').'.pdf');
    }

    private function getMetricPayload(string $metric, string $objid, Carbon $startDate, Carbon $endDate): array
    {
        $histData = $this->fetchHistoricDataByObjid($objid, $startDate, $endDate);
        $rawValues = $this->extractSeriesForMetric($histData, $metric);
        $coverageValues = $this->extractCoverageValues($histData);
        $trend = $this->buildDailyTrendForMetric($histData, $metric, $startDate, $endDate);

        if ($metric === 'ping') {
            // PRTG historicdata for ping exposes coverage per interval.
            // Use it as uptime proxy for selected period.
            $uptimePercent = ! empty($coverageValues)
                ? round(array_sum($coverageValues) / count($coverageValues), 2)
                : (! empty($rawValues) ? 100.0 : null);

            return [
                'ok' => true,
                'metric' => 'ping',
                'available' => true,
                'uptime_percent' => $uptimePercent,
                'trend' => $trend,
            ];
        }

        if ($metric === 'disk') {
            $diskSummary = $this->summarizeDiskPeriodFromHistoricData($histData);
            $stats = $diskSummary['percent'];
        } else {
            $stats = $this->summarizeSeries($rawValues);
        }

        $bars = $metric === 'traffic'
            ? $this->buildRelativeBars($stats)
            : $stats;

        $payload = [
            'ok' => true,
            'metric' => $metric,
            'available' => true,
            'stats' => $stats,
            'bars' => $bars,
            'trend' => $trend,
        ];

        if ($metric === 'disk') {
            $payload['stats_free_gb'] = $diskSummary['free_gb'];
            $snapshot = $this->fetchDiskSensorSnapshot($objid);
            $lastvalue = $snapshot['lastvalue'] ?? '';
            $payload['disk_meta'] = [
                'lastvalue' => $lastvalue !== '' ? $lastvalue : null,
                'sensor' => ($snapshot['sensor'] ?? '') !== '' ? $snapshot['sensor'] : null,
                'capacity_hint' => $this->parseDiskCapacityHintFromLastvalue($lastvalue),
            ];
        }

        return $payload;
    }

    private function resolvePeriodFromRequest(Request $request): array
    {
        $startRaw = trim((string) $request->query('start_date'));
        $endRaw = trim((string) $request->query('end_date'));

        if ($startRaw === '' || $endRaw === '') {
            return [null, null, 'Periode belum dipilih.'];
        }

        try {
            $startDate = Carbon::parse($startRaw)->startOfDay();
            $endDate = Carbon::parse($endRaw)->endOfDay();
        } catch (Throwable) {
            return [null, null, 'Format periode tidak valid.'];
        }

        if ($startDate->gt($endDate)) {
            return [null, null, 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'];
        }

        return [$startDate, $endDate, null];
    }

    private function summarizeSeries(array $values): array
    {
        if (empty($values)) {
            return [
                'min' => null,
                'max' => null,
                'avg' => null,
            ];
        }

        return [
            'min' => round(min($values), 2),
            'max' => round(max($values), 2),
            'avg' => round(array_sum($values) / count($values), 2),
        ];
    }

    private function buildDailyTrendForMetric(array $histData, string $metric, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $grouped = [];
        $sequentialValues = [];

        foreach ($histData as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = match ($metric) {
                'disk' => $this->pickDiskFreePercentFromHistoricRow($row),
                'cpu' => $this->pickCpuPercentFromHistoricRow($row),
                'ram' => $this->pickRamPercentFromHistoricRow($row),
                'traffic' => $this->pickTrafficBytesPerSecondFromHistoricRow($row),
                default => null,
            };

            if ($value === null && $metric !== 'disk') {
                $value = $this->pickLegacySingleValueRaw($row);
            }

            if ($value === null) {
                continue;
            }

            if ($metric === 'ram') {
                // API PRTG RAM bernilai persen free; chart menampilkan RAM load.
                $value = max(0, min(100, 100 - $value));
            }

            $sequentialValues[] = (float) $value;

            $timestamp = $this->parseHistoricRowDateTime($row);
            if ($timestamp === null) {
                continue;
            }

            $hourKey = $timestamp->copy()->minute(0)->second(0)->format('Y-m-d H:00:00');
            if (! isset($grouped[$hourKey])) {
                $grouped[$hourKey] = [];
            }
            $grouped[$hourKey][] = (float) $value;
        }

        if (! empty($grouped)) {
            ksort($grouped);
            $labels = [];
            $lineMin = [];
            $lineMax = [];
            $lineAvg = [];

            foreach ($grouped as $hour => $values) {
                if (empty($values)) {
                    continue;
                }

                $labels[] = Carbon::parse($hour)->format('d/m H:i');
                $lineMin[] = round(min($values), 2);
                $lineMax[] = round(max($values), 2);
                $lineAvg[] = round(array_sum($values) / count($values), 2);
            }

            return [
                'labels' => $labels,
                'min' => $lineMin,
                'max' => $lineMax,
                'avg' => $lineAvg,
            ];
        }

        if (empty($sequentialValues)) {
            return ['labels' => [], 'min' => [], 'max' => [], 'avg' => []];
        }

        // Fallback jika datetime dari PRTG tidak terbaca: gunakan bucket berurutan.
        $bucketCount = min(12, count($sequentialValues));
        $chunkSize = (int) ceil(count($sequentialValues) / $bucketCount);
        $labels = [];
        $lineMin = [];
        $lineMax = [];
        $lineAvg = [];

        $rangeMinutes = ($startDate !== null && $endDate !== null && $startDate->lte($endDate))
            ? max(1, (int) $startDate->copy()->diffInMinutes($endDate->copy()))
            : null;

        for ($i = 0; $i < $bucketCount; $i++) {
            $values = array_slice($sequentialValues, $i * $chunkSize, $chunkSize);
            if (empty($values)) {
                continue;
            }

            if ($startDate !== null && $rangeMinutes !== null) {
                $offsetMinutes = (int) floor(($i / max($bucketCount - 1, 1)) * $rangeMinutes);
                $labels[] = $startDate->copy()->addMinutes($offsetMinutes)->format('d/m H:i');
            } else {
                $labels[] = 'P' . ($i + 1);
            }
            $lineMin[] = round(min($values), 2);
            $lineMax[] = round(max($values), 2);
            $lineAvg[] = round(array_sum($values) / count($values), 2);
        }

        return [
            'labels' => $labels,
            'min' => $lineMin,
            'max' => $lineMax,
            'avg' => $lineAvg,
        ];
    }

    /**
     * Disk free: urutkan histori menurut waktu.
     * percent: min/max/avg = % bebas awal, akhir, selisih.
     * free_gb: idem untuk ruang bebas (dari kolom Free Bytes API), dalam GiB (ditampilkan sebagai GB).
     *
     * @return array{percent: array{min: float|null, max: float|null, avg: float|null}, free_gb: array{min: float|null, max: float|null, avg: float|null}}
     */
    private function summarizeDiskPeriodFromHistoricData(array $histData): array
    {
        $series = $this->extractDiskTimelineFromHistoricData($histData);

        if (empty($series)) {
            $empty = ['min' => null, 'max' => null, 'avg' => null];

            return [
                'percent' => $empty,
                'free_gb' => $empty,
            ];
        }

        $first = $series[0];
        $last = $series[count($series) - 1];
        $startVal = $first['v'];
        $endVal = $last['v'];

        $percent = [
            'min' => round($startVal, 2),
            'max' => round($endVal, 2),
            'avg' => round($endVal - $startVal, 2),
        ];

        $freeGb = [
            'min' => null,
            'max' => null,
            'avg' => null,
        ];

        $firstB = $first['b'] ?? null;
        $lastB = $last['b'] ?? null;
        if (is_numeric($firstB) && is_numeric($lastB) && (float) $firstB > 0 && (float) $lastB > 0) {
            $gib = (float) (1024 ** 3);
            $fb = (float) $firstB;
            $lb = (float) $lastB;
            $freeGb['min'] = round($fb / $gib, 2);
            $freeGb['max'] = round($lb / $gib, 2);
            $freeGb['avg'] = round(($lb - $fb) / $gib, 2);
        }

        return [
            'percent' => $percent,
            'free_gb' => $freeGb,
        ];
    }

    /**
     * @return list<array{ts: ?Carbon, idx: int, v: float, b: ?float}>
     */
    private function extractDiskTimelineFromHistoricData(array $histData): array
    {
        $out = [];
        $idx = 0;

        foreach ($histData as $row) {
            if (! is_array($row)) {
                continue;
            }

            $v = $this->pickDiskFreePercentFromHistoricRow($row);
            if ($v === null) {
                continue;
            }

            $out[] = [
                'ts' => $this->parseHistoricRowDateTime($row),
                'idx' => $idx,
                'v' => $v,
                'b' => $this->pickDiskFreeBytesFromHistoricRow($row),
            ];
            $idx++;
        }

        usort($out, function (array $a, array $b): int {
            if ($a['ts'] !== null && $b['ts'] !== null) {
                $cmp = $a['ts']->getTimestamp() <=> $b['ts']->getTimestamp();

                return $cmp !== 0 ? $cmp : ($a['idx'] <=> $b['idx']);
            }
            if ($a['ts'] !== null) {
                return -1;
            }
            if ($b['ts'] !== null) {
                return 1;
            }

            return $a['idx'] <=> $b['idx'];
        });

        return $out;
    }

    private function parseHistoricRowDateTime(array $row): ?Carbon
    {
        $raw = $row['datetime'] ?? null;
        if (! is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $startToken = trim(explode(' - ', $raw, 2)[0] ?? $raw);

        foreach (['d.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', \DateTimeInterface::ATOM] as $format) {
            try {
                return Carbon::createFromFormat($format, $startToken);
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse($startToken);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildRelativeBars(array $stats): array
    {
        $max = $stats['max'] ?? null;

        if (! is_numeric($max) || (float) $max <= 0) {
            return [
                'min' => null,
                'max' => null,
                'avg' => null,
            ];
        }

        $max = (float) $max;

        return [
            'min' => isset($stats['min']) ? round((((float) $stats['min']) / $max) * 100, 2) : null,
            'max' => 100.0,
            'avg' => isset($stats['avg']) ? round((((float) $stats['avg']) / $max) * 100, 2) : null,
        ];
    }

    /**
     * Ambil deret nilai histori sesuai metric. Tanpa usecaption, JSON PRTG mengulang key value/value_raw
     * sehingga value_raw sering salah (mis. 0 % downtime). Pakai kolom bertCaption + fallback.
     *
     * @return list<float>
     */
    private function extractSeriesForMetric(array $histData, string $metric): array
    {
        $values = [];

        foreach ($histData as $row) {
            if (! is_array($row)) {
                continue;
            }

            $val = match ($metric) {
                'disk' => $this->pickDiskFreePercentFromHistoricRow($row),
                'cpu' => $this->pickCpuPercentFromHistoricRow($row),
                'ram' => $this->pickRamPercentFromHistoricRow($row),
                'traffic' => $this->pickTrafficBytesPerSecondFromHistoricRow($row),
                default => null,
            };

            if ($val === null && $metric !== 'disk') {
                // Disk: jangan pakai value_raw — sering channel salah (mirip CPU/load kecil).
                $val = $this->pickLegacySingleValueRaw($row);
            }

            if ($val !== null) {
                $values[] = $val;
            }
        }

        return $values;
    }

    private function pickLegacySingleValueRaw(array $row): ?float
    {
        $raw = $row['value_raw'] ?? null;

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function pickDiskFreePercentFromHistoricRow(array $row): ?float
    {
        $strictScore = -1;
        $strict = null;
        $looseScore = -1;
        $loose = null;

        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $kl = strtolower($key);
            if (str_contains($kl, 'downtime')) {
                continue;
            }
            if (str_contains($kl, 'datetime')) {
                continue;
            }
            if (str_ends_with($key, '_raw')) {
                continue;
            }
            if ($kl === 'coverage') {
                continue;
            }
            if ($this->historicKeyLooksNonDisk($kl)) {
                continue;
            }

            $v = $this->normalizePercentishScalar($value);
            if ($v === null) {
                continue;
            }

            $score = $this->diskHistoricCaptionScore($kl);

            if ($score > $looseScore) {
                $looseScore = $score;
                $loose = $v;
            }
            if ($score >= 5 && $score > $strictScore) {
                $strictScore = $score;
                $strict = $v;
            }
        }

        if ($strict !== null) {
            return $strict;
        }

        if ($loose !== null && $looseScore >= 3) {
            return $loose;
        }

        return null;
    }

    private function diskHistoricCaptionScore(string $keyLower): int
    {
        $score = 0;
        if (str_contains($keyLower, 'disk free')) {
            $score += 8;
        }
        if (str_contains($keyLower, '/') && (str_contains($keyLower, 'disk') || str_contains($keyLower, 'free') || str_contains($keyLower, 'label'))) {
            $score += 6;
        }
        if (str_contains($keyLower, 'percent')) {
            $score += 5;
        }
        if (str_contains($keyLower, 'available')) {
            $score += 4;
        }
        if (str_contains($keyLower, 'free')) {
            $score += 3;
        }
        if (str_contains($keyLower, 'disk')) {
            $score += 2;
        }
        if (str_contains($keyLower, 'volume') || str_contains($keyLower, 'drive')) {
            $score += 2;
        }
        if (str_contains($keyLower, 'used') && ! str_contains($keyLower, 'free')) {
            $score -= 10;
        }

        return $score;
    }

    /**
     * Kolom Free Bytes pada historicdata PRTG (nilai mentah byte atau string bertegi GB/MB).
     */
    private function pickDiskFreeBytesFromHistoricRow(array $row): ?float
    {
        $bestScore = -1;
        $best = null;

        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $kl = strtolower($key);
            if (str_contains($kl, 'downtime') || str_contains($kl, 'datetime') || str_ends_with($key, '_raw') || $kl === 'coverage') {
                continue;
            }

            if ($this->historicKeyLooksNonDisk($kl)) {
                continue;
            }

            if ((bool) preg_match('/\btotal\b/u', $kl) && ! str_contains($kl, 'free')) {
                continue;
            }

            $bytes = $this->normalizeDiskFreeBytesScalar($value);
            if ($bytes === null) {
                continue;
            }

            $score = $this->diskFreeBytesCaptionScore($kl);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $bytes;
            }
        }

        return ($best !== null && $bestScore >= 4) ? $best : null;
    }

    private function diskFreeBytesCaptionScore(string $keyLower): int
    {
        $score = 0;

        if (str_contains($keyLower, 'free') && str_contains($keyLower, 'byte')) {
            $score += 12;
        }
        if (str_contains($keyLower, 'bytes')) {
            $score += 6;
        }
        if (str_contains($keyLower, 'free space') && ! str_contains($keyLower, '%')) {
            $score += 3;
        }
        if (str_contains($keyLower, 'percent') || str_contains($keyLower, '%')) {
            $score -= 15;
        }
        if ((bool) preg_match('/\btotal\b/u', $keyLower) && ! str_contains($keyLower, 'free')) {
            $score -= 20;
        }

        return $score;
    }

    private function normalizeDiskFreeBytesScalar(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $n = (float) $value;
            if ($n <= 0 || ! is_finite($n)) {
                return null;
            }
            if ($n >= 1_048_576) {
                return $n;
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $s = trim(str_replace("\xc2\xa0", ' ', $value));
        if ($s === '') {
            return null;
        }

        if (preg_match('/^([\d][\d\s.,]*)\s*(tb|tbyte|gb|gbyte|gib|mb|mbyte|mib|kb|kbyte|byte|bytes)?\s*$/iu', $s, $m)) {
            $unitRaw = isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : '';
            $n = $this->normalizePrtgDecimalStringForStorage($m[1]);
            if ($n <= 0) {
                return null;
            }
            if ($unitRaw === '' || $unitRaw === 'byte' || $unitRaw === 'bytes') {
                return $n >= 1_048_576 ? $n : null;
            }

            return $this->prtgStorageUnitToBytes($n, $unitRaw);
        }

        return null;
    }

    private function normalizePrtgDecimalStringForStorage(string $raw): float
    {
        $raw = trim(preg_replace('/\s+/u', '', $raw) ?? '');
        if ($raw === '') {
            return 0.0;
        }

        if (str_contains($raw, '.') && str_contains($raw, ',')) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',') && ! str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }

    private function prtgStorageUnitToBytes(float $n, string $u): ?float
    {
        $mult = match (true) {
            str_starts_with($u, 'tb') || str_starts_with($u, 'tby') || $u === 'tbyte' => 1024.0 ** 4,
            str_starts_with($u, 'gb') || str_starts_with($u, 'gby') || str_starts_with($u, 'gib') || $u === 'gbyte' => 1024.0 ** 3,
            str_starts_with($u, 'mb') || str_starts_with($u, 'mby') || str_starts_with($u, 'mib') || $u === 'mbyte' => 1024.0 ** 2,
            str_starts_with($u, 'kb') || str_starts_with($u, 'kib') || str_starts_with($u, 'kby') || $u === 'kbyte' => 1024.0,
            default => null,
        };

        if ($mult === null) {
            return null;
        }

        return $n * $mult;
    }

    /**
     * PRTG historicdata kadang mengirim persen sebagai string "24 %" atau "24,12%".
     */
    private function normalizePercentishScalar(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $v = (float) $value;

            return ($v >= 0 && $v <= 100) ? $v : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $s = trim(str_replace("\xc2\xa0", ' ', $value));
        if ($s === '') {
            return null;
        }

        if (preg_match('/^(-?\d+(?:[.,]\d+)?)\s*%?\s*$/u', $s, $matches)) {
            $v = (float) str_replace(',', '.', $matches[1]);

            return ($v >= 0 && $v <= 100) ? $v : null;
        }

        return null;
    }

    private function historicKeyLooksNonDisk(string $keyLower): bool
    {
        if (preg_match('/(^|[^a-z])cpu([^a-z]|$)/', $keyLower)) {
            return true;
        }
        if (str_contains($keyLower, 'processor load') || str_contains($keyLower, 'cpu load')) {
            return true;
        }
        if (str_contains($keyLower, 'traffic') || str_contains($keyLower, 'bandwidth')) {
            return true;
        }
        if ((str_contains($keyLower, 'memory') || preg_match('/(^|[^a-z])ram([^a-z]|$)/', $keyLower)) && ! str_contains($keyLower, 'disk')) {
            return true;
        }

        return false;
    }

    private function pickCpuPercentFromHistoricRow(array $row): ?float
    {
        $bestScore = -1;
        $best = null;

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }
            $kl = strtolower($key);
            if (str_contains($kl, 'downtime')) {
                continue;
            }
            if (str_ends_with($key, '_raw')) {
                continue;
            }
            $v = (float) $value;
            if ($v < 0 || $v > 100) {
                continue;
            }
            $score = 0;
            if (str_contains($kl, 'cpu')) {
                $score += 4;
            }
            if (str_contains($kl, 'load')) {
                $score += 4;
            }
            if (str_contains($kl, 'percent')) {
                $score += 2;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $v;
            }
        }

        return $best;
    }

    private function pickRamPercentFromHistoricRow(array $row): ?float
    {
        $bestScore = -1;
        $best = null;

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }
            $kl = strtolower($key);
            if (str_contains($kl, 'downtime')) {
                continue;
            }
            if (str_ends_with($key, '_raw')) {
                continue;
            }
            $v = (float) $value;
            if ($v < 0 || $v > 100) {
                continue;
            }
            $score = 0;
            if (str_contains($kl, 'physical')) {
                $score += 4;
            }
            if (str_contains($kl, 'memory')) {
                $score += 3;
            }
            if (str_contains($kl, 'ram')) {
                $score += 2;
            }
            if (str_contains($kl, 'percent')) {
                $score += 2;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $v;
            }
        }

        return $best;
    }

    private function pickTrafficBytesPerSecondFromHistoricRow(array $row): ?float
    {
        foreach ($row as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }
            $kl = strtolower($key);
            if (str_contains($kl, 'total') && str_contains($kl, 'speed')) {
                return (float) $value;
            }
        }

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }
            $kl = strtolower($key);
            if (str_contains($kl, 'speed')) {
                return (float) $value;
            }
        }

        return null;
    }

    private function extractCoverageValues(array $histData): array
    {
        $values = [];

        foreach ($histData as $row) {
            if (! is_array($row)) {
                continue;
            }

            $raw = $row['coverage_raw'] ?? null;

            if (is_numeric($raw)) {
                $values[] = round(((float) $raw) / 100, 2);

                continue;
            }

            $coverage = $row['coverage'] ?? null;
            if (is_string($coverage) && preg_match('/(-?\d+(?:[.,]\d+)?)\s*%/u', $coverage, $matches)) {
                $values[] = round((float) str_replace(',', '.', $matches[1]), 2);
            }
        }

        return $values;
    }

    private function getDevicesForStatistics(): \Illuminate\Support\Collection
    {
        return DcDrcDevice::query()
            ->where(function ($query): void {
                $query->whereRaw("TRIM(COALESCE(objid_ping, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_cpu, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_ram, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_traffic, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_diskfree, '')) != ''");
            })
            ->orderBy('server_name')
            ->get([
                'id',
                'server_name',
                'objid_ping',
                'objid_cpu',
                'objid_ram',
                'objid_traffic',
                'objid_diskfree',
            ]);
    }

    private function metricLabelForPdf(string $metric): string
    {
        return match ($metric) {
            'cpu' => 'CPU Load',
            'ram' => 'RAM Load',
            'traffic' => 'Traffic',
            'disk' => 'Disk Free',
            default => strtoupper($metric),
        };
    }

    private function metricUnitForPdf(string $metric): string
    {
        return match ($metric) {
            'cpu', 'ram', 'disk' => '%',
            'traffic' => 'B/s',
            default => '',
        };
    }

    private function resolveObjidByMetric(DcDrcDevice $device, string $metric): string
    {
        return match ($metric) {
            'ping' => trim((string) $device->objid_ping),
            'cpu' => trim((string) $device->objid_cpu),
            'ram' => trim((string) $device->objid_ram),
            'traffic' => trim((string) $device->objid_traffic),
            'disk' => trim((string) $device->objid_diskfree),
            default => '',
        };
    }

    private function fetchHistoricDataByObjid(string $objid, Carbon $startDate, Carbon $endDate): array
    {
        [$baseUrl, $username, $passhash, $verifySsl] = $this->getPrtgConfig();
        $cursor = $startDate->copy();
        $allRows = [];

        // PRTG historicdata dapat memotong hasil pada range panjang.
        // Ambil per-chunk 7 hari agar seluruh periode tetap terambil.
        while ($cursor->lte($endDate)) {
            $chunkEnd = $cursor->copy()->addDays(6)->endOfDay();
            if ($chunkEnd->gt($endDate)) {
                $chunkEnd = $endDate->copy();
            }

            $chunkRows = $this->requestHistoricDataChunk(
                $baseUrl,
                $username,
                $passhash,
                $verifySsl,
                $objid,
                $cursor,
                $chunkEnd
            );

            if (! empty($chunkRows)) {
                $allRows = array_merge($allRows, $chunkRows);
            }

            $nextCursor = $chunkEnd->copy()->addSecond();
            if ($nextCursor->lte($cursor)) {
                break;
            }
            $cursor = $nextCursor;
        }

        if (empty($allRows)) {
            return [];
        }

        // Dedup berdasarkan datetime untuk menghindari overlap antar chunk.
        $seen = [];
        $deduplicated = [];
        foreach ($allRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['datetime'] ?? ''));
            if ($key !== '') {
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }

            $deduplicated[] = $row;
        }

        return $deduplicated;
    }

    private function requestHistoricDataChunk(
        string $baseUrl,
        string $username,
        string $passhash,
        bool $verifySsl,
        string $objid,
        Carbon $chunkStart,
        Carbon $chunkEnd
    ): array {
        $response = Http::timeout(25)
            ->acceptJson()
            ->withOptions(['verify' => $verifySsl])
            ->get(rtrim($baseUrl, '/') . '/api/historicdata.json', [
                'id' => $objid,
                'sdate' => $chunkStart->format('Y-m-d-H-i-s'),
                'edate' => $chunkEnd->format('Y-m-d-H-i-s'),
                'avg' => 3600,
                'usecaption' => 1,
                'username' => $username,
                'passhash' => $passhash,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('PRTG historicdata non-success status: ' . $response->status());
        }

        $json = $response->json();

        return is_array($json['histdata'] ?? null) ? $json['histdata'] : [];
    }

    private function getPrtgConfig(): array
    {
        $baseUrl = trim((string) config('services.prtg.base_url'));
        $username = trim((string) config('services.prtg.username'));
        $passhash = trim((string) config('services.prtg.passhash'));
        $verifySsl = (bool) config('services.prtg.verify_ssl', true);

        if ($baseUrl === '' || $username === '' || $passhash === '') {
            throw new \RuntimeException('PRTG config is incomplete.');
        }

        return [$baseUrl, $username, $passhash, $verifySsl];
    }

    /**
     * @return array{lastvalue: string, sensor: string}
     */
    private function fetchDiskSensorSnapshot(string $objid): array
    {
        if ($objid === '') {
            return ['lastvalue' => '', 'sensor' => ''];
        }

        try {
            [$baseUrl, $username, $passhash, $verifySsl] = $this->getPrtgConfig();
            $response = Http::timeout(20)
                ->acceptJson()
                ->withOptions(['verify' => $verifySsl])
                ->get(rtrim($baseUrl, '/') . '/api/table.json', [
                    'content' => 'sensors',
                    'output' => 'json',
                    'columns' => 'device,sensor,lastvalue,status',
                    'filter_objid' => $objid,
                    'count' => 1,
                    'username' => $username,
                    'passhash' => $passhash,
                ]);

            if (! $response->successful()) {
                return ['lastvalue' => '', 'sensor' => ''];
            }

            $json = $response->json();
            $rows = is_array($json['sensors'] ?? null) ? $json['sensors'] : [];
            $row = $rows[0] ?? null;

            if (! is_array($row)) {
                return ['lastvalue' => '', 'sensor' => ''];
            }

            return [
                'lastvalue' => trim((string) ($row['lastvalue'] ?? '')),
                'sensor' => trim((string) ($row['sensor'] ?? '')),
            ];
        } catch (Throwable) {
            return ['lastvalue' => '', 'sensor' => ''];
        }
    }

    private function parseDiskCapacityHintFromLastvalue(string $lastvalue): ?string
    {
        $lastvalue = trim($lastvalue);
        if ($lastvalue === '') {
            return null;
        }

        if (preg_match('/\(([^)]+)\)/u', $lastvalue, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?\s*(?:GB|TB|MB))\s+free\s+of\s+(\d+(?:[.,]\d+)?\s*(?:GB|TB|MB))/iu', $lastvalue, $matches)) {
            return sprintf('%s bebas / %s total', trim($matches[1]), trim($matches[2]));
        }

        return null;
    }
}
