<?php

namespace App\Support;

use App\Models\Kantor;
use App\Models\KasKantor;
use App\Models\NetworkMonitorSetting;

class NetworkStatusSummary
{
    public static function get(): array
    {
        $threshold = NetworkMonitorSetting::highPingThresholdMs();
        $kasRto = KasKantor::query()
            ->where('is_active', true)
            ->where('ping_status', 'rto')
            ->with('kantor:id,nama_kantor')
            ->orderBy('kode_kas')
            ->get()
            ->map(fn (KasKantor $kas) => [
                'type' => 'Kantor Kas',
                'code' => $kas->kode_kas,
                'name' => $kas->nama_kas ?: $kas->kode_kas,
                'parent' => $kas->kantor?->nama_kantor,
                'latency' => null,
                'checked_at' => $kas->ping_checked_at?->toIso8601String(),
            ])->values()->all();
        $cabangRto = Kantor::query()
            ->where('is_active', true)
            ->where('ping_status', 'rto')
            ->orderBy('kode_kantor')
            ->get()
            ->map(fn (Kantor $kantor) => [
                'type' => 'Kantor Cabang',
                'code' => $kantor->kode_kantor,
                'name' => $kantor->nama_kantor,
                'parent' => null,
                'latency' => null,
                'checked_at' => $kantor->ping_checked_at?->toIso8601String(),
            ])->values()->all();
        $slowCabang = Kantor::query()
            ->where('is_active', true)
            ->where('ping_status', 'reply')
            ->where('ping_latency_ms', '>', $threshold)
            ->orderBy('kode_kantor')
            ->get()
            ->map(fn (Kantor $kantor) => [
                'type' => 'Kantor Cabang',
                'code' => $kantor->kode_kantor,
                'name' => $kantor->nama_kantor,
                'parent' => null,
                'latency' => $kantor->ping_latency_ms,
                'checked_at' => $kantor->ping_checked_at?->toIso8601String(),
            ]);
        $slowKas = KasKantor::query()
            ->where('is_active', true)
            ->where('ping_status', 'reply')
            ->where('ping_latency_ms', '>', $threshold)
            ->with('kantor:id,nama_kantor')
            ->orderBy('kode_kas')
            ->get()
            ->map(fn (KasKantor $kas) => [
                'type' => 'Kantor Kas',
                'code' => $kas->kode_kas,
                'name' => $kas->nama_kas ?: $kas->kode_kas,
                'parent' => $kas->kantor?->nama_kantor,
                'latency' => $kas->ping_latency_ms,
                'checked_at' => $kas->ping_checked_at?->toIso8601String(),
            ]);
        $slow = $slowCabang->concat($slowKas)->values()->all();

        return [
            'kas_rto' => count($kasRto),
            'kas_rto_list' => $kasRto,
            'cabang_rto' => count($cabangRto),
            'cabang_rto_list' => $cabangRto,
            'slow' => count($slow),
            'slow_list' => $slow,
            'high_ping_threshold_ms' => $threshold,
        ];
    }
}
