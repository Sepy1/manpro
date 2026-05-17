<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DcDrcDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class MonitoringController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $payload = $this->getMonitoringPayload();

        return view('pages.dashboard.aset-ti.monitoring', [
            'rows' => $payload['rows'],
            'errorMessage' => $payload['errorMessage'],
            'lastUpdatedAt' => $payload['lastUpdatedAt'],
        ]);
    }

    public function data(): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $payload = $this->getMonitoringPayload();

        return response()->json([
            'rows' => $payload['rows'],
            'errorMessage' => $payload['errorMessage'],
            'lastUpdatedAt' => $payload['lastUpdatedAt']->format('d M Y H:i:s'),
        ]);
    }

    private function fetchPrtgSensors(): array
    {
        $baseUrl = rtrim((string) config('services.prtg.base_url'), '/');
        $username = (string) config('services.prtg.username');
        $passhash = (string) config('services.prtg.passhash');
        $verifySsl = filter_var(config('services.prtg.verify_ssl', false), FILTER_VALIDATE_BOOL);

        if ($baseUrl === '' || $username === '' || $passhash === '') {
            throw new \RuntimeException('PRTG environment variables are incomplete.');
        }

        $request = Http::timeout(15);
        if (!$verifySsl) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get($baseUrl . '/api/table.json', [
            'content' => 'sensors',
            'output' => 'json',
            'columns' => 'objid,device,sensor,lastvalue,status,lastvalue_raw,status_raw',
            'username' => $username,
            'passhash' => $passhash,
        ]);

        $response->throw();

        $payload = $response->json();
        return is_array($payload['sensors'] ?? null) ? $payload['sensors'] : [];
    }

    private function buildMonitoringRowsByObjid(array $sensors): \Illuminate\Support\Collection
    {
        $sensorByObjid = collect($sensors)
            ->filter(fn (array $sensor): bool => trim((string) ($sensor['objid'] ?? '')) !== '')
            ->mapWithKeys(function (array $sensor): array {
                return [trim((string) $sensor['objid']) => [
                    'lastvalue' => $this->sanitizeValue($sensor['lastvalue'] ?? null),
                    'status' => $this->sanitizeValue($sensor['status'] ?? null),
                ]];
            });

        $devices = DcDrcDevice::query()
            ->where(function ($query): void {
                $query->whereRaw("TRIM(COALESCE(objid_ping, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_cpu, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_ram, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_traffic, '')) != ''")
                    ->orWhereRaw("TRIM(COALESCE(objid_diskfree, '')) != ''");
            })
            ->orderBy('server_name')
            ->get([
                'server_name',
                'objid_ping',
                'objid_cpu',
                'objid_ram',
                'objid_traffic',
                'objid_diskfree',
            ]);

        return $devices->map(function (DcDrcDevice $device) use ($sensorByObjid): array {
            $ping = $this->getSensorByObjid($sensorByObjid, $device->objid_ping);
            $cpu = $this->getSensorByObjid($sensorByObjid, $device->objid_cpu);
            $ram = $this->getSensorByObjid($sensorByObjid, $device->objid_ram);
            $traffic = $this->getSensorByObjid($sensorByObjid, $device->objid_traffic);
            $diskFree = $this->getSensorByObjid($sensorByObjid, $device->objid_diskfree);

            return [
                'server' => $device->server_name,
                'status' => $ping['status'] ?? '-',
                'cpu_load' => $cpu['lastvalue'] ?? '-',
                'free_ram' => $ram['lastvalue'] ?? '-',
                'traffic' => $traffic['lastvalue'] ?? '-',
                'disk_free' => $diskFree['lastvalue'] ?? '-',
            ];
        });
    }

    private function getSensorByObjid(\Illuminate\Support\Collection $sensorByObjid, mixed $objid): ?array
    {
        $normalized = trim((string) $objid);
        if ($normalized === '') {
            return null;
        }

        return $sensorByObjid->get($normalized);
    }

    private function sanitizeValue(mixed $value): string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? '-' : $normalized;
    }

    private function getMonitoringPayload(): array
    {
        $rows = [];
        $errorMessage = null;

        try {
            $sensors = $this->fetchPrtgSensors();
            $rows = $this->buildMonitoringRowsByObjid($sensors)
                ->sortBy('server', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        } catch (Throwable $e) {
            $errorMessage = 'Gagal mengambil data monitoring dari API PRTG. Silakan cek koneksi/credential PRTG.';
        }

        return [
            'rows' => $rows,
            'errorMessage' => $errorMessage,
            'lastUpdatedAt' => now(),
        ];
    }

}
