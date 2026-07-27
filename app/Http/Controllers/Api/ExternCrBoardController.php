<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExternCrStatus;
use App\Http\Controllers\Controller;
use App\Models\ExternCr;
use App\Support\ExternCrHistoryRecorder;
use App\Support\ExternCrStatusChangeAttachmentStorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternCrBoardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(1000, (int) $request->integer('limit', 300)));

        $items = ExternCr::query()
            ->with(['division:id,name', 'application:id,name', 'changeReason:id,name', 'vendorPic:id,name'])
            ->orderByDesc('tanggal')
            ->orderByDesc('daily_sequence')
            ->limit($limit)
            ->get();

        return response()->json([
            'ok' => true,
            'board' => [
                'title' => 'CR Eksternal',
                'columns' => $this->columns($items),
                'status_options' => $this->statusOptions(),
                'meta' => [
                    'total_items' => $items->count(),
                    'generated_at' => now()->toIso8601String(),
                ],
            ],
        ]);
    }

    public function show(ExternCr $externCr): JsonResponse
    {
        $externCr->load([
            'division:id,name',
            'application:id,name',
            'changeReason:id,name',
            'vendorPic:id,name',
            'attachments',
            'histories' => fn ($q) => $q->latest()->limit(20),
        ]);

        return response()->json([
            'ok' => true,
            'item' => $this->toItemPayload($externCr, true),
        ]);
    }

    public function updateStatus(Request $request, ExternCr $externCr): JsonResponse
    {
        $validator = validator($request->all(), [
            'status' => ['required', Rule::enum(ExternCrStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'status' => 'status',
            'note' => 'catatan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first() ?: 'Validasi gagal.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();
        $newStatus = ExternCrStatus::from((string) $validated['status']);
        $oldStatus = $externCr->status;

        $noteRaw = isset($validated['note']) ? trim((string) $validated['note']) : '';
        $note = $noteRaw !== '' ? $noteRaw : null;

        if ($oldStatus !== $newStatus) {
            ExternCrHistoryRecorder::statusChanged($externCr, $oldStatus, $newStatus, $note);
            $externCr->update(['status' => $newStatus]);
            $externCr->refresh();
            $message = 'Status diperbarui.';
        } elseif (ExternCrStatusChangeAttachmentStorer::hasUploads($request)) {
            $history = ExternCrHistoryRecorder::statusChanged($externCr, $oldStatus, $oldStatus, $note);
            ExternCrStatusChangeAttachmentStorer::storeForHistory($externCr, $history, $request);
            $message = 'Lampiran status disimpan.';
        } else {
            $message = 'Status tidak berubah.';
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'item' => $this->toItemPayload($externCr->fresh(), false),
        ]);
    }

    private function columns(iterable $items): array
    {
        $statusMap = [];
        foreach (ExternCrStatus::cases() as $status) {
            $statusMap[$status->value] = [];
        }

        foreach ($items as $item) {
            $statusMap[$item->status->value][] = $this->toItemPayload($item, false);
        }

        return [
            ['key' => ExternCrStatus::Open->value, 'title' => 'Backlog', 'status' => ExternCrStatus::Open->value, 'items' => $statusMap[ExternCrStatus::Open->value]],
            ['key' => ExternCrStatus::VendorDevelopment->value, 'title' => 'On Progress', 'status' => ExternCrStatus::VendorDevelopment->value, 'items' => $statusMap[ExternCrStatus::VendorDevelopment->value]],
            ['key' => ExternCrStatus::Uat->value, 'title' => 'Testing UAT', 'status' => ExternCrStatus::Uat->value, 'items' => $statusMap[ExternCrStatus::Uat->value]],
            ['key' => ExternCrStatus::GoLive->value, 'title' => 'Go Live', 'status' => ExternCrStatus::GoLive->value, 'items' => $statusMap[ExternCrStatus::GoLive->value]],
            ['key' => ExternCrStatus::Closed->value, 'title' => 'Done', 'status' => ExternCrStatus::Closed->value, 'items' => $statusMap[ExternCrStatus::Closed->value]],
        ];
    }

    private function statusOptions(): array
    {
        return array_map(static fn (ExternCrStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], ExternCrStatus::cases());
    }

    private function toItemPayload(ExternCr $externCr, bool $includeDetail = false): array
    {
        $payload = [
            'id' => $externCr->id,
            'nomor' => $externCr->nomor,
            'tanggal' => optional($externCr->tanggal)->toDateString(),
            'daily_sequence' => $externCr->daily_sequence,
            'nama' => $externCr->nama,
            'bidang' => $externCr->bidang,
            'status' => [
                'value' => $externCr->status->value,
                'label' => $externCr->status->label(),
            ],
            'prioritas' => $externCr->prioritas,
            'division' => $externCr->division?->only(['id', 'name']),
            'application' => $externCr->application?->only(['id', 'name']),
            'change_reason' => $externCr->changeReason?->only(['id', 'name']),
            'vendor_pic' => $externCr->vendorPic?->only(['id', 'name']),
            'updated_at' => optional($externCr->updated_at)?->toIso8601String(),
        ];

        if ($includeDetail) {
            $payload['deskripsi_permintaan'] = $externCr->deskripsi_permintaan;
            $payload['kondisi_saat_ini'] = $externCr->kondisi_saat_ini;
            $payload['perubahan_diharapkan'] = $externCr->perubahan_diharapkan;
            $payload['risiko_bila_tidak'] = $externCr->risiko_bila_tidak;
            $payload['attachments'] = $externCr->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size_bytes' => $attachment->size_bytes,
                'download_url' => null,
            ])->values();
            $payload['histories'] = $externCr->histories->map(fn ($history) => [
                'id' => $history->id,
                'event' => $history->event,
                'properties' => $history->properties,
                'created_at' => optional($history->created_at)?->toIso8601String(),
            ])->values();
        }

        return $payload;
    }
}
