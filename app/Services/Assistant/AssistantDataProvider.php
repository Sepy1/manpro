<?php

namespace App\Services\Assistant;

use App\Http\Controllers\Admin\AsetTiController;
use App\Models\CctvDevice;
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
            default => ['error' => 'Tool tidak dikenal: '.$name],
        };
    }
}
