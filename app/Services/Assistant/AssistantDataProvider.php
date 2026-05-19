<?php

namespace App\Services\Assistant;

use App\Http\Controllers\Admin\AsetTiController;
use App\Models\CctvDevice;
use App\Models\DcDrcDevice;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssistantDataProvider
{
    public function __construct(
        private AsetTiController $asetTiController,
    ) {}

    /**
     * Ringkas metrik PRTG per server agar hemat token.
     *
     * @return array{success: bool, updated_at: string, servers: array<int, array<string, mixed>>, error?: string}
     */
    public function getDataCenterMetrics(?string $serverFilter = null): array
    {
        try {
            $rows = $this->asetTiController->getDataCenterRowsForAssistant();
        } catch (Throwable $e) {
            Log::warning('Assistant Data Center fetch failed', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Gagal mengambil data Data Center / PRTG.',
                'updated_at' => now()->toIso8601String(),
                'servers' => [],
            ];
        }

        if ($serverFilter !== null && $serverFilter !== '') {
            $needle = mb_strtolower($serverFilter);
            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($row['server'] ?? '')), $needle);
            }));
        }

        $servers = [];
        foreach ($rows as $row) {
            $traffic = is_array($row['traffic'] ?? null) ? $row['traffic'] : [];
            $points = is_array($traffic['points'] ?? null) ? $traffic['points'] : [];
            $lastPoints = array_slice($points, -6);

            $servers[] = [
                'server' => $row['server'] ?? '-',
                'cpu_percent' => $row['cpu']['percent'] ?? null,
                'cpu_text' => $row['cpu']['text'] ?? '-',
                'ram_percent' => $row['ram']['percent'] ?? null,
                'ram_text' => $row['ram']['text'] ?? '-',
                'disk_percent' => $row['disk']['percent'] ?? null,
                'disk_text' => $row['disk']['text'] ?? '-',
                'traffic_latest_mbps' => $traffic['latest_mbps'] ?? null,
                'traffic_recent_mbps' => array_map(static fn (array $p): array => [
                    't' => $p['label'] ?? '',
                    'v' => $p['value'] ?? null,
                ], $lastPoints),
            ];
        }

        return [
            'success' => true,
            'updated_at' => now()->toIso8601String(),
            'servers' => $servers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCctvSummary(): array
    {
        $base = CctvDevice::query()
            ->whereNotNull('dvr_brand')
            ->whereRaw("TRIM(dvr_brand) != ''")
            ->where('dvr_brand', '!=', '-');

        $total = CctvDevice::count();
        $withDvr = (clone $base)->count();

        $byConnection = CctvDevice::query()
            ->selectRaw("CASE WHEN connection_status IS NULL OR TRIM(connection_status) = '' OR TRIM(connection_status) = '-' THEN 'Tidak diketahui' ELSE connection_status END as label")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $topBrands = CctvDevice::query()
            ->whereNotNull('dvr_brand')
            ->whereRaw("TRIM(dvr_brand) != ''")
            ->where('dvr_brand', '!=', '-')
            ->selectRaw('dvr_brand as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'total_devices' => $total,
            'devices_with_dvr_filled' => $withDvr,
            'by_connection' => $byConnection->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total])->all(),
            'top_dvr_brands' => $topBrands->map(fn ($r) => ['brand' => $r->label, 'total' => (int) $r->total])->all(),
        ];
    }

    /**
     * @return array{devices: array<int, array<string, mixed>>}
     */
    public function searchCctvDevices(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['devices' => [], 'note' => 'Berikan kata kunci cabang, kantor, merk DVR, atau status.'];
        }

        $limit = max(1, min($limit, 25));

        $devices = CctvDevice::query()
            ->where(function ($q) use ($query): void {
                $q->where('branch', 'like', '%'.$query.'%')
                    ->orWhere('office', 'like', '%'.$query.'%')
                    ->orWhere('dvr_brand', 'like', '%'.$query.'%')
                    ->orWhere('connection_status', 'like', '%'.$query.'%')
                    ->orWhere('device_status', 'like', '%'.$query.'%')
                    ->orWhere('monitor', 'like', '%'.$query.'%');
            })
            ->orderBy('branch')
            ->orderBy('office')
            ->limit($limit)
            ->get([
                'id', 'branch', 'office', 'dvr_brand', 'channel_count', 'harddisk', 'monitor',
                'connection_status', 'device_status', 'notes',
            ]);

        return [
            'devices' => $devices->map(fn (CctvDevice $d) => $d->toArray())->all(),
        ];
    }

    /**
     * @return array{projects: array<int, array<string, mixed>>}
     */
    public function getProjectsSnapshot(?string $status = null, ?string $nameSearch = null, int $limit = 15): array
    {
        $limit = max(1, min($limit, 25));

        $q = Project::query()->orderByDesc('updated_at');

        if ($status !== null && $status !== '') {
            $q->where('status', 'like', '%'.$status.'%');
        }

        if ($nameSearch !== null && $nameSearch !== '') {
            $q->where('name', 'like', '%'.$nameSearch.'%');
        }

        $projects = $q->limit($limit)->get([
            'id', 'name', 'category', 'division', 'status', 'deadline', 'follow_up', 'pic', 'period_start', 'period_end',
        ]);

        return [
            'projects' => $projects->map(function (Project $p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'category' => $p->category,
                    'division' => $p->division,
                    'status' => $p->status,
                    'deadline' => $p->deadline?->format('Y-m-d'),
                    'period_start' => $p->period_start?->format('Y-m-d'),
                    'period_end' => $p->period_end?->format('Y-m-d'),
                    'follow_up' => $p->follow_up ? mb_substr(strip_tags((string) $p->follow_up), 0, 200) : null,
                    'pic' => $p->pic,
                ];
            })->all(),
        ];
    }

    /**
     * Daftar perangkat dari inventaris DC-DRC (tabel dc_drc_devices).
     *
     * @return array{devices: array<int, array<string, mixed>>, note?: string}
     */
    public function listDcDrcDevices(?string $deviceType, ?string $keyword, int $limit): array
    {
        $limit = max(1, min($limit, 60));
        $keyword = $keyword !== null ? trim($keyword) : '';

        $q = DcDrcDevice::query()
            ->with(['vmHost:id,server_name'])
            ->orderBy('server_name');

        if ($deviceType !== null && trim($deviceType) !== '') {
            $dt = mb_strtolower(trim($deviceType));
            if (in_array($dt, ['vm host', 'vmhost', 'host'], true)) {
                $q->whereRaw("LOWER(TRIM(device_type)) = 'vm host'");
            } elseif (in_array($dt, ['vm', 'guest'], true)) {
                $q->whereRaw("LOWER(TRIM(device_type)) = 'vm'");
            } elseif (in_array($dt, ['baremetal', 'bare metal'], true)) {
                $q->where(function ($inner): void {
                    $inner->whereRaw("LOWER(TRIM(device_type)) = 'baremetal'")
                        ->orWhereRaw("LOWER(TRIM(device_type)) = 'bare metal'");
                });
            }
        }

        if ($keyword !== '') {
            $q->where(function ($inner) use ($keyword): void {
                $inner->where('server_name', 'like', '%'.$keyword.'%')
                    ->orWhere('host_server', 'like', '%'.$keyword.'%')
                    ->orWhere('ip_address', 'like', '%'.$keyword.'%')
                    ->orWhere('site', 'like', '%'.$keyword.'%')
                    ->orWhere('system_role', 'like', '%'.$keyword.'%');
            });
        }

        $rows = $q->limit($limit)->get();

        $devices = $rows->map(function (DcDrcDevice $d) {
            return [
                'id' => $d->id,
                'server_name' => $d->server_name,
                'device_type' => $d->device_type,
                'host_server' => $d->host_server,
                'vm_host_id' => $d->vm_host_id,
                'vm_host_name' => $d->vmHost?->server_name,
                'ip_address' => $d->ip_address,
                'cpu_cores' => $d->cpu_cores,
                'ram_gb' => $d->ram_gb,
                'storage_gb' => $d->storage_gb,
                'site' => $d->site,
                'environment' => $d->environment,
                'status' => $d->status,
                'has_prtg_cpu' => trim((string) $d->objid_cpu) !== '',
                'has_prtg_ram' => trim((string) $d->objid_ram) !== '',
                'has_prtg_disk' => trim((string) $d->objid_diskfree) !== '',
                'has_prtg_traffic' => trim((string) $d->objid_traffic) !== '',
            ];
        })->all();

        return [
            'devices' => $devices,
            'note' => 'Untuk VM Host + pemakaian CPU/RAM/disk real-time dari PRTG, gunakan tool analyze_vm_hosts_capacity.',
        ];
    }

    /**
     * VM Host dari DC-DRC + metrik PRTG untuk menilai kandidat penempatan VM baru.
     *
     * @return array<string, mixed>
     */
    public function analyzeVmHostsCapacity(float $cpuMax, float $ramMax, float $diskMax): array
    {
        $cpuMax = max(50.0, min($cpuMax, 99.0));
        $ramMax = max(50.0, min($ramMax, 99.0));
        $diskMax = max(50.0, min($diskMax, 99.0));

        $metricsByServer = [];
        $dc = $this->getDataCenterMetrics(null);
        if (($dc['success'] ?? false) === true) {
            foreach ($dc['servers'] ?? [] as $s) {
                $name = trim((string) ($s['server'] ?? ''));
                if ($name !== '') {
                    $metricsByServer[mb_strtolower($name)] = $s;
                }
            }
        }

        $hosts = DcDrcDevice::query()
            ->whereRaw("LOWER(TRIM(device_type)) = 'vm host'")
            ->withCount([
                'hostedVms as hosted_vm_count' => function ($q): void {
                    $q->whereRaw("LOWER(TRIM(device_type)) = 'vm'");
                },
            ])
            ->orderBy('server_name')
            ->get();

        $vmHosts = [];

        foreach ($hosts as $host) {
            $name = trim((string) $host->server_name);
            $key = mb_strtolower($name);
            $m = $metricsByServer[$key] ?? null;

            $cpu = is_array($m) ? $m['cpu_percent'] : null;
            $ram = is_array($m) ? $m['ram_percent'] : null;
            $disk = is_array($m) ? $m['disk_percent'] : null;
            $traffic = is_array($m) ? ($m['traffic_latest_mbps'] ?? null) : null;

            $monitored = $cpu !== null || $ram !== null || $disk !== null;
            $stressed = false;
            $reasons = [];

            if ($cpu !== null && $cpu >= $cpuMax) {
                $stressed = true;
                $reasons[] = 'cpu >= '.$cpuMax.'%';
            }
            if ($ram !== null && $ram >= $ramMax) {
                $stressed = true;
                $reasons[] = 'ram >= '.$ramMax.'%';
            }
            if ($disk !== null && $disk >= $diskMax) {
                $stressed = true;
                $reasons[] = 'disk >= '.$diskMax.'%';
            }

            $candidate = 'unknown';
            if ($monitored) {
                $candidate = $stressed ? 'limited' : 'favorable';
                if (! $stressed && $cpu !== null && $ram !== null && $disk !== null
                    && $cpu < ($cpuMax - 15) && $ram < ($ramMax - 15) && $disk < ($diskMax - 10)) {
                    $candidate = 'strong';
                }
            }

            $vmHosts[] = [
                'id' => $host->id,
                'server_name' => $name,
                'hosted_vm_count' => (int) $host->hosted_vm_count,
                'spec_cpu_cores' => $host->cpu_cores,
                'spec_ram_gb' => $host->ram_gb,
                'spec_storage_gb' => $host->storage_gb,
                'ip_address' => $host->ip_address,
                'site' => $host->site,
                'prtg_live' => [
                    'cpu_percent' => $cpu,
                    'ram_percent' => $ram,
                    'disk_percent' => $disk,
                    'traffic_latest_mbps' => $traffic,
                ],
                'capacity_hint' => [
                    'candidate_level' => $candidate,
                    'reasons_if_limited' => $reasons,
                    'thresholds_used' => [
                        'cpu_under_percent' => $cpuMax,
                        'ram_under_percent' => $ramMax,
                        'disk_under_percent' => $diskMax,
                    ],
                ],
            ];
        }

        return [
            'success' => $dc['success'] ?? false,
            'prtg_error' => $dc['error'] ?? null,
            'updated_at' => $dc['updated_at'] ?? now()->toIso8601String(),
            'vm_hosts' => $vmHosts,
            'legend' => [
                'strong' => 'Metrik PRTG ada dan CPU/RAM/disk cukup di bawah ambang — kandidat baik untuk VM baru (tetap validasi operasional).',
                'favorable' => 'Di bawah ambang stres; masih masuk akal untuk pertimbangan.',
                'limited' => 'Satu atau lebih metrik mendekati/melewati ambang — kurang ideal untuk VM tambahan tanpa penyesuaian.',
                'unknown' => 'Belum ada metrik PRTG yang cocok untuk nama host (samakan nama server dengan baris PRTG / objid).',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function runTool(string $name, array $arguments): array
    {
        return match ($name) {
            'get_data_center_metrics' => $this->getDataCenterMetrics(isset($arguments['server_filter']) ? (string) $arguments['server_filter'] : null),
            'get_cctv_summary' => $this->getCctvSummary(),
            'search_cctv_devices' => $this->searchCctvDevices(
                isset($arguments['query']) ? (string) $arguments['query'] : '',
                isset($arguments['limit']) ? (int) $arguments['limit'] : 15
            ),
            'get_projects_snapshot' => $this->getProjectsSnapshot(
                isset($arguments['status']) ? (string) $arguments['status'] : null,
                isset($arguments['name_search']) ? (string) $arguments['name_search'] : null,
                isset($arguments['limit']) ? (int) $arguments['limit'] : 15
            ),
            'list_dc_drc_devices' => $this->listDcDrcDevices(
                isset($arguments['device_type']) ? (string) $arguments['device_type'] : null,
                isset($arguments['keyword']) ? (string) $arguments['keyword'] : null,
                isset($arguments['limit']) ? (int) $arguments['limit'] : 40
            ),
            'analyze_vm_hosts_capacity' => $this->analyzeVmHostsCapacity(
                isset($arguments['cpu_headroom_under']) ? (float) $arguments['cpu_headroom_under'] : 75.0,
                isset($arguments['ram_headroom_under']) ? (float) $arguments['ram_headroom_under'] : 80.0,
                isset($arguments['disk_headroom_under']) ? (float) $arguments['disk_headroom_under'] : 85.0
            ),
            default => ['error' => 'Tool tidak dikenal: '.$name],
        };
    }
}
