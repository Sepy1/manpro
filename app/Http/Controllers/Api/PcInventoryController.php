<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePcInventoryRequest;
use App\Models\Kantor;
use App\Models\PcInventory;
use Illuminate\Http\JsonResponse;

class PcInventoryController extends Controller
{
    public function offices(): JsonResponse
    {
        $offices = Kantor::query()
            ->where('is_active', true)
            ->with(['kasKantor' => fn ($query) => $query
                ->where('is_active', true)
                ->select(['id', 'kantor_id', 'kode_kas', 'nama_kas'])])
            ->orderBy('kode_kantor')
            ->get(['id', 'kode_kantor', 'nama_kantor']);

        return response()->json([
            'ok' => true,
            'data' => $offices->map(fn (Kantor $kantor) => [
                'id' => $kantor->id,
                'kode_kantor' => $kantor->kode_kantor,
                'nama_kantor' => $kantor->nama_kantor,
                'kantor_kas' => $kantor->kasKantor->map(fn ($kasKantor) => [
                    'id' => $kasKantor->id,
                    'kode_kas' => $kasKantor->kode_kas,
                    'nama_kas' => $kasKantor->nama_kas,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(StorePcInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $kantor = Kantor::query()->findOrFail($validated['kantor_id']);
        $kasKantor = isset($validated['kas_kantor_id'])
            ? $kantor->kasKantor()->findOrFail($validated['kas_kantor_id'])
            : null;

        $inventory = PcInventory::query()->updateOrCreate(
            ['machine_guid' => $validated['machine_guid']],
            [
                ...collect($validated)->except(['machine_guid'])->all(),
                'kode_kantor' => $kantor->kode_kantor,
                'kode_kas' => $kasKantor?->kode_kas,
                'last_seen_at' => now(),
                'sender_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => $inventory->wasRecentlyCreated
                ? 'Data inventaris berhasil disimpan.'
                : 'Data inventaris berhasil diperbarui.',
            'data' => [
                'id' => $inventory->id,
                'machine_guid' => $inventory->machine_guid,
                'last_seen_at' => $inventory->last_seen_at?->toISOString(),
            ],
        ]);
    }
}
