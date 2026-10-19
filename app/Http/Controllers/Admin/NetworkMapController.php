<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kantor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NetworkMapController extends Controller
{
    public function index(): View
    {
        return view('pages.dashboard.network-map', [
            'locations' => $this->locations(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'locations' => $this->locations(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function locations(): Collection
    {
        $kantors = Kantor::query()
            ->where('is_active', true)
            ->with(['kasKantor' => fn ($query) => $query
                ->where('is_active', true)])
            ->orderBy('nama_kantor')
            ->get();

        $locations = $kantors
            ->flatMap(function (Kantor $kantor) {
                $items = collect();
                $problemKas = $kantor->kasKantor
                    ->where('ping_status', 'rto')
                    ->map(fn ($kas) => [
                        'code' => $kas->kode_kas,
                        'name' => $kas->nama_kas ?: $kas->kode_kas,
                        'ping_checked_at' => $kas->ping_checked_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all();

                if ($kantor->latitude !== null && $kantor->longitude !== null) {
                    $items->push([
                        'id' => 'cabang-'.$kantor->id,
                        'type' => 'cabang',
                        'code' => $kantor->kode_kantor,
                        'name' => $kantor->nama_kantor,
                        'parent' => null,
                        'latitude' => (float) $kantor->latitude,
                        'longitude' => (float) $kantor->longitude,
                        'ping_status' => $kantor->ping_status,
                        'ping_latency_ms' => $kantor->ping_latency_ms,
                        'ping_checked_at' => $kantor->ping_checked_at?->toIso8601String(),
                        'problem_kas' => $problemKas,
                    ]);
                }

                foreach ($kantor->kasKantor as $kas) {
                    if ($kas->latitude === null || $kas->longitude === null) {
                        continue;
                    }

                    $items->push([
                        'id' => 'kas-'.$kas->id,
                        'type' => 'kas',
                        'code' => $kas->kode_kas,
                        'name' => $kas->nama_kas ?: $kas->kode_kas,
                        'parent' => $kantor->nama_kantor,
                        'latitude' => (float) $kas->latitude,
                        'longitude' => (float) $kas->longitude,
                        'ping_status' => $kas->ping_status,
                        'ping_latency_ms' => $kas->ping_latency_ms,
                        'ping_checked_at' => $kas->ping_checked_at?->toIso8601String(),
                        'problem_kas' => [],
                    ]);
                }

                return $items;
            })
            ->values();

        return $locations;
    }
}
