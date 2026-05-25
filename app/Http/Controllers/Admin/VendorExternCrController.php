<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExternCrHistoryEvent;
use App\Enums\ExternCrStatus;
use App\Http\Controllers\Controller;
use App\Models\ExternCr;
use App\Models\ExternCrAttachment;
use App\Models\ExternCrHistory;
use App\Support\ExternCrHistoryRecorder;
use App\Support\ExternCrMergedPdfBuilder;
use App\Support\ExternCrVendorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorExternCrController extends Controller
{
    public function index(Request $request): View
    {
        $vendorUser = ExternCrVendorAccess::ensureVendorUser();

        $query = ExternCrVendorAccess::scopeAssignedTo(ExternCr::query(), $vendorUser)
            ->with(['division', 'application', 'changeReason', 'vendorPic'])
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $kw = trim((string) $request->get('keyword'));
                if ($kw === '') {
                    return;
                }
                $like = '%'.addcslashes($kw, '%_\\').'%';
                $q->where(function ($q) use ($like) {
                    $q->where('nomor', 'like', $like)
                        ->orWhere('nama', 'like', $like)
                        ->orWhere('bidang', 'like', $like)
                        ->orWhereHas('division', fn ($d) => $d->where('name', 'like', $like));
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('daily_sequence');

        return view('pages.dashboard.cr-eksternal.vendor-index', [
            'items' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function detailModalFragment(ExternCr $externCr): View
    {
        ExternCrVendorAccess::authorizeAssignedCr($externCr);

        $externCr->load(['application', 'changeReason', 'attachments', 'vendorPic']);

        return view('pages.dashboard.cr-eksternal.partials.detail-modal-body', [
            'cr' => $externCr,
            'latestStatusChangeNote' => $this->latestStatusChangeNoteForCurrentStatus($externCr),
            'allowedStatuses' => ExternCrStatus::vendorPipelineCases(),
            'attachmentContext' => 'vendor',
        ]);
    }

    public function updateStatus(Request $request, ExternCr $externCr): JsonResponse
    {
        ExternCrVendorAccess::authorizeAssignedCr($externCr);

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(ExternCrStatus::vendorPipelineValues())],
            'note' => ['nullable', 'string', 'max:5000'],
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
        }

        return response()->json([
            'ok' => true,
            'message' => $oldStatus === $newStatus
                ? 'Status tidak berubah.'
                : 'Status diperbarui.',
            'status_label' => $externCr->status->label(),
        ]);
    }

    public function printPdf(ExternCr $externCr): Response
    {
        ExternCrVendorAccess::authorizeAssignedCr($externCr);

        return ExternCrMergedPdfBuilder::streamedInlineResponse($externCr);
    }

    public function downloadAttachment(ExternCr $externCr, ExternCrAttachment $attachment): StreamedResponse
    {
        ExternCrVendorAccess::authorizeAssignedCr($externCr);
        abort_unless($attachment->extern_cr_id === $externCr->id, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name ?? basename($attachment->path));
    }

    private function latestStatusChangeNoteForCurrentStatus(ExternCr $externCr): ?string
    {
        $currentValue = $externCr->status->value;

        $rows = ExternCrHistory::query()
            ->where('extern_cr_id', $externCr->id)
            ->where('event', ExternCrHistoryEvent::StatusChanged->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['properties']);

        foreach ($rows as $row) {
            $props = $row->properties ?? [];
            if (($props['to'] ?? null) !== $currentValue) {
                continue;
            }

            $n = isset($props['note']) && is_string($props['note']) ? trim($props['note']) : '';

            return $n !== '' ? $n : null;
        }

        return null;
    }
}
