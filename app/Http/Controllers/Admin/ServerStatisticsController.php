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

    private function getMetricPayload(string $metric, string $objid, Carbon $startDate, Carbon $endDate): array
    {
        $histData = $this->fetchHistoricDataByObjid($objid, $startDate, $endDate);
        $rawValues = $this->extractSeriesForMetric($histData, $metric);
        $coverageValues = $this->extractCoverageValues($histData);

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
            ];
        }

        if ($metric === 'disk') {
            // Disk: min = % bebas di awal periode, max = % bebas di akhir periode, avg = selisih (akhir − awal).
            $stats = $this->summarizeDiskPeriodFromHistoricData($histData);
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
        ];

        if ($metric === 'disk') {
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

    /**
     * Disk free: urutkan histori menurut waktu; min/max/avg API = % bebas awal, % bebas akhir, selisih (penambahan/pengurangan relatif bebas).
     *
     * @return array{min: float|null, max: float|null, avg: float|null}
     */
    private function summarizeDiskPeriodFromHistoricData(array $histData): array
    {
        $series = $this->extractDiskTimelineFromHistoricData($histData);

        if (empty($series)) {
            return [
                'min' => null,
                'max' => null,
                'avg' => null,
            ];
        }

        $first = $series[0];
        $last = $series[count($series) - 1];
        $startVal = $first['v'];
        $endVal = $last['v'];

        return [
            'min' => round($startVal, 2),
            'max' => round($endVal, 2),
            'avg' => round($endVal - $startVal, 2),
        ];
    }

    /**
     * @return list<array{ts: ?Carbon, idx: int, v: float}>
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

        $response = Http::timeout(25)
            ->acceptJson()
            ->withOptions(['verify' => $verifySsl])
            ->get(rtrim($baseUrl, '/') . '/api/historicdata.json', [
                'id' => $objid,
                'sdate' => $startDate->format('Y-m-d-H-i-s'),
                'edate' => $endDate->format('Y-m-d-H-i-s'),
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
