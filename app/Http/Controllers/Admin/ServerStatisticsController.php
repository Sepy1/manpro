<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DcDrcDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
            $payload = $this->getMetricPayloadFromCache(
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

    private function getMetricPayloadFromCache(string $metric, string $objid, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = implode(':', [
            'server-statistics',
            $metric,
            $objid,
            $startDate->format('YmdHis'),
            $endDate->format('YmdHis'),
            'avg3600',
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($metric, $objid, $startDate, $endDate): array {
            $histData = $this->fetchHistoricDataByObjid($objid, $startDate, $endDate);
            $rawValues = $this->extractRawValues($histData);
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

            $stats = $this->summarizeSeries($rawValues);
            $bars = $metric === 'traffic'
                ? $this->buildRelativeBars($stats)
                : $stats;

            return [
                'ok' => true,
                'metric' => $metric,
                'available' => true,
                'stats' => $stats,
                'bars' => $bars,
            ];
        });
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

    private function extractRawValues(array $histData): array
    {
        $values = [];

        foreach ($histData as $row) {
            $raw = $row['value_raw'] ?? null;

            if (is_numeric($raw)) {
                $values[] = (float) $raw;
            }
        }

        return $values;
    }

    private function extractCoverageValues(array $histData): array
    {
        $values = [];

        foreach ($histData as $row) {
            $raw = $row['coverage_raw'] ?? null;

            if (is_numeric($raw)) {
                $values[] = round(((float) $raw) / 100, 2);
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
}
