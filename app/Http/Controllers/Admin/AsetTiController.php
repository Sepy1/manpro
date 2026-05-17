<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DcDrcDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AsetTiController extends Controller
{
    public function dataCenter(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.aset-ti.data-center', array_merge(
            [
                'metricsUrl' => route('admin.aset-ti.data-center.metrics'),
            ],
            app(DcDrcDeviceController::class)->dashboardDonuts()
        ));
    }

    public function dataCenterMetrics(): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        try {
            $rows = $this->buildDataCenterRows();

            return response()->json([
                'rows' => $rows,
                'errorMessage' => null,
                'updatedAt' => now()->format('d M Y H:i:s'),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to build data center dashboard payload.', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return response()->json([
                'rows' => [],
                'errorMessage' => 'Gagal mengambil data dashboard Data Center dari PRTG.',
                'updatedAt' => now()->format('d M Y H:i:s'),
            ], 500);
        }
    }

    private function buildDataCenterRows(): array
    {
        $devices = DcDrcDevice::query()
            ->where(function ($query): void {
                $query->whereRaw("TRIM(COALESCE(objid_cpu, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_ram, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_diskfree, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_traffic, '')) != ''");
            })
            ->orderBy('server_name')
            ->get([
                'server_name',
                'objid_cpu',
                'objid_ram',
                'objid_diskfree',
                'objid_traffic',
            ]);

        $rows = [];
        foreach ($devices as $device) {
            $cpuObjid = trim((string) $device->objid_cpu);
            $ramObjid = trim((string) $device->objid_ram);
            $diskObjid = trim((string) $device->objid_diskfree);
            $trafficObjid = trim((string) $device->objid_traffic);

            $rows[] = [
                'server' => $device->server_name ?? '-',
                'cpu' => $this->fetchPercentMetric($cpuObjid, 'cpu'),
                'ram' => $this->fetchPercentMetric($ramObjid, 'ram'),
                'disk' => $this->fetchPercentMetric($diskObjid, 'disk'),
                'traffic' => $this->fetchTrafficSeries($trafficObjid),
            ];
        }

        return $rows;
    }

    private function fetchPercentMetric(string $objid, string $metric): array
    {
        if ($objid === '') {
            return [
                'available' => false,
                'percent' => null,
                'text' => '-',
            ];
        }

        try {
            $sensor = $this->fetchSensorByObjid($objid);

            if ($sensor === null) {
                return [
                    'available' => false,
                    'percent' => null,
                    'text' => '-',
                ];
            }

            $raw = $sensor['lastvalue_raw'] ?? null;
            $percent = is_numeric($raw)
                ? max(0, min(100, (float) $raw))
                : $this->parsePercentFromLastvalue((string) ($sensor['lastvalue'] ?? ''));

            return [
                'available' => true,
                'percent' => $percent,
                'text' => trim((string) ($sensor['lastvalue'] ?? '-')),
            ];
        } catch (Throwable $e) {
            Log::warning('Data center percent metric failed.', [
                'metric' => $metric,
                'objid' => $objid,
                'message' => $e->getMessage(),
            ]);

            return [
                'available' => false,
                'percent' => null,
                'text' => '-',
            ];
        }
    }

    private function fetchTrafficSeries(string $objid): array
    {
        if ($objid === '') {
            return [
                'available' => false,
                'latest_mbps' => null,
                'points' => [],
            ];
        }

        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDay()->startOfDay();

        try {
            [$baseUrl, $username, $passhash, $verifySsl] = $this->getPrtgConfig();

            $response = Http::retry(1, 250)
                ->timeout(60)
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
                return $this->normalizeTrafficPayload($this->fallbackTrafficSeriesFromLiveSensor($objid));
            }

            $json = $response->json();
            $rows = is_array($json['histdata'] ?? null) ? $json['histdata'] : [];
            $points = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $mbps = $this->extractTrafficMbpsFromHistoricRow($row);
                if ($mbps === null) {
                    continue;
                }

                $label = $this->extractHourRangeLabel((string) ($row['datetime'] ?? ''));
                $points[] = [
                    'label' => $label,
                    'value' => $mbps,
                ];
            }

            if (empty($points)) {
                $fallback = $this->fallbackTrafficSeriesFromLiveSensor($objid);
                if (! empty($fallback['points'])) {
                    return $this->normalizeTrafficPayload($fallback);
                }

                return $this->normalizeTrafficPayload([
                    'available' => true,
                    'latest_mbps' => 0.0,
                    'points' => $this->generateFlatTrafficPoints(0.0),
                ]);
            }

            $payload = [
                'available' => true,
                'latest_mbps' => ! empty($points) ? $points[array_key_last($points)]['value'] : null,
                'points' => $points,
            ];

            return $this->normalizeTrafficPayload($payload);
        } catch (Throwable $e) {
            Log::warning('Data center traffic metric failed.', [
                'objid' => $objid,
                'message' => $e->getMessage(),
            ]);

            return $this->normalizeTrafficPayload($this->fallbackTrafficSeriesFromLiveSensor($objid));
        }
    }

    private function fetchSensorByObjid(string $objid): ?array
    {
        [$baseUrl, $username, $passhash, $verifySsl] = $this->getPrtgConfig();
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withOptions(['verify' => $verifySsl])
                ->get(rtrim($baseUrl, '/') . '/api/table.json', [
                    'content' => 'sensors',
                    'output' => 'json',
                    // Selain traffic historis, metric diambil dari table sensors.
                    'columns' => 'device,sensor,lastvalue,status,objid,lastvalue_raw',
                    'filter_objid' => $objid,
                    'count' => 1,
                    'username' => $username,
                    'passhash' => $passhash,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            $rows = is_array($json['sensors'] ?? null) ? $json['sensors'] : [];

            return $rows[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('Data center sensor lookup failed.', [
                'objid' => $objid,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parsePercentFromLastvalue(string $lastvalue): ?float
    {
        if ($lastvalue === '') {
            return null;
        }

        if (! preg_match('/-?\d+(?:[.,]\d+)?/', $lastvalue, $matches)) {
            return null;
        }

        $normalized = str_replace(',', '.', $matches[0]);
        if (! is_numeric($normalized)) {
            return null;
        }

        return max(0, min(100, (float) $normalized));
    }

    private function extractTrafficMbpsFromHistoricRow(array $row): ?float
    {
        $bestScore = -1;
        $bestValue = null;

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }

            $keyLower = strtolower($key);
            if (! str_contains($keyLower, 'speed')) {
                continue;
            }

            $score = 4;
            if (str_contains($keyLower, 'total')) {
                $score += 4;
            } elseif (str_contains($keyLower, 'in') || str_contains($keyLower, 'out')) {
                $score += 2;
            }

            $candidate = $this->normalizeCaptionSpeedToMbps((float) $value);
            if ($score > $bestScore || ($score === $bestScore && ($bestValue === null || $candidate > $bestValue))) {
                $bestScore = $score;
                $bestValue = $candidate;
            }
        }

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! str_ends_with($key, '_raw') || ! is_numeric($value)) {
                continue;
            }

            $keyLower = strtolower($key);
            if ($keyLower === 'datetime_raw' || $keyLower === 'coverage_raw') {
                continue;
            }

            $score = 0;
            if (str_contains($keyLower, 'traffic')) {
                $score += 3;
            }
            if (str_contains($keyLower, 'speed')) {
                $score += 3;
            }
            if (str_contains($keyLower, 'bandwidth')) {
                $score += 3;
            }
            if (str_contains($keyLower, 'total')) {
                $score += 1;
            }

            $valueText = (string) ($row[substr($key, 0, -4)] ?? '');
            $mbpsFromText = $this->parseTrafficMbpsFromValueString($valueText);
            if ($mbpsFromText !== null) {
                // Channel dengan unit bit/s jauh lebih valid untuk grafik traffic.
                $score += 6;
                $candidate = $mbpsFromText;
            } else {
                // Abaikan channel persen/coverage yang sering muncul sebagai "0 %".
                if (str_contains($valueText, '%')) {
                    continue;
                }
                $candidate = (((float) $value) * 8) / 1_000_000;
            }

            if ($score > $bestScore || ($score === $bestScore && ($bestValue === null || $candidate > $bestValue))) {
                $bestScore = $score;
                $bestValue = $candidate;
            }
        }

        if ($bestValue !== null) {
            return round(max(0, $bestValue), 6);
        }

        foreach ($row as $value) {
            if (! is_string($value)) {
                continue;
            }

            $mbps = $this->parseTrafficMbpsFromValueString($value);
            if ($mbps !== null) {
                return round(max(0, $mbps), 6);
            }
        }

        return null;
    }

    private function normalizeCaptionSpeedToMbps(float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        // Pada PRTG historicdata (usecaption=1), kolom "(Speed)" adalah bytes/s.
        // Konversi bytes/s -> Mbit/s.
        return ($value * 8) / 1_000_000;
    }

    private function parseTrafficMbpsFromValueString(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! preg_match('/(-?\d+(?:[.,]\d+)?)\s*(gbit\/s|mbit\/s|kbit\/s|bit\/s)/i', $value, $matches)) {
            return null;
        }

        $number = str_replace(',', '.', $matches[1]);
        if (! is_numeric($number)) {
            return null;
        }

        $numeric = (float) $number;
        $unit = strtolower($matches[2]);

        return match ($unit) {
            'gbit/s' => $numeric * 1000,
            'mbit/s' => $numeric,
            'kbit/s' => $numeric / 1000,
            'bit/s' => $numeric / 1_000_000,
            default => null,
        };
    }

    private function extractHourRangeLabel(string $datetime): string
    {
        if ($datetime === '') {
            return '-';
        }

        $parts = explode(' - ', $datetime);
        $start = $parts[0] ?? $datetime;
        $startParts = explode(' ', $start);
        $time = $startParts[1] ?? '';

        return substr($time, 0, 5) ?: $datetime;
    }

    private function fallbackTrafficSeriesFromLiveSensor(string $objid): array
    {
        $sensor = $this->fetchSensorByObjid($objid);
        $raw = $sensor['lastvalue_raw'] ?? null;
        $latest = is_numeric($raw)
            ? round((((float) $raw) * 8) / 1_000_000, 6)
            : $this->parseTrafficMbpsFromValueString((string) ($sensor['lastvalue'] ?? ''));

        if ($latest === null) {
            $latest = 0.0;
        }

        return [
            'available' => true,
            'latest_mbps' => $latest,
            'points' => $this->generateFlatTrafficPoints($latest),
        ];
    }

    private function normalizeTrafficPayload(array $payload): array
    {
        $points = is_array($payload['points'] ?? null) ? $payload['points'] : [];
        $latest = $payload['latest_mbps'] ?? null;

        if (empty($points)) {
            $latestValue = is_numeric($latest) ? (float) $latest : 0.0;

            return [
                'available' => true,
                'latest_mbps' => $latestValue,
                'points' => $this->generateFlatTrafficPoints($latestValue),
            ];
        }

        if (! is_numeric($latest)) {
            $lastPoint = $points[array_key_last($points)] ?? null;
            $latest = is_array($lastPoint) && is_numeric($lastPoint['value'] ?? null)
                ? (float) $lastPoint['value']
                : 0.0;
        }

        return [
            'available' => true,
            'latest_mbps' => (float) $latest,
            'points' => $points,
        ];
    }

    private function generateFlatTrafficPoints(?float $value): array
    {
        if ($value === null) {
            return [];
        }

        $points = [];
        for ($i = 23; $i >= 0; $i--) {
            $label = now()->subHours($i)->format('H:i');
            $points[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $points;
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
}
