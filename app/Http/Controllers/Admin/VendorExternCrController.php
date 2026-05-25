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
use App\Support\ExternCrStatusChangeAttachmentStorer;
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
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = ExternCrStatus::tryFrom((string) $request->get('status'));
                if ($status !== null) {
                    $q->where('status', $status->value);
                }
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('daily_sequence');

        return view('pages.dashboard.cr-eksternal.vendor-index', [
            'items' => $query->paginate(15)->withQueryString(),
            'statusFilter' => ExternCrStatus::tryFrom((string) $request->get('status', '')),
            'statusFilterOptions' => ExternCrStatus::cases(),
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

        $validator = Validator::make($request->all(), array_merge([
            'status' => ['required', Rule::in(ExternCrStatus::vendorPipelineValues())],
            'note' => ['nullable', 'string', 'max:5000'],
        ], ExternCrStatusChangeAttachmentStorer::validationRules()));

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
            $history = ExternCrHistoryRecorder::statusChanged($externCr, $oldStatus, $newStatus, $note);
            $externCr->update(['status' => $newStatus]);
            $attachmentCount = ExternCrStatusChangeAttachmentStorer::storeForHistory($externCr, $history, $request);
            $externCr->refresh();
            $baseMessage = 'Status diperbarui.';
        } elseif (ExternCrStatusChangeAttachmentStorer::hasUploads($request)) {
            $history = ExternCrHistoryRecorder::statusChanged($externCr, $oldStatus, $oldStatus, $note);
            $attachmentCount = ExternCrStatusChangeAttachmentStorer::storeForHistory($externCr, $history, $request);
            $baseMessage = 'Lampiran disimpan.';
        } else {
            $attachmentCount = 0;
            $baseMessage = 'Status tidak berubah.';
        }

        return response()->json([
            'ok' => true,
            'message' => ExternCrStatusChangeAttachmentStorer::appendAttachmentSummaryToMessage($baseMessage, $attachmentCount),
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

    public function historyModalFragment(ExternCr $externCr): View
    {
        ExternCrVendorAccess::authorizeAssignedCr($externCr);

        $limit = 60;
        $baseQuery = $externCr->histories()
            ->where('event', ExternCrHistoryEvent::StatusChanged->value)
            ->with(['user', 'attachments'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $totalCount = (clone $baseQuery)->count();
        $histories = (clone $baseQuery)->limit($limit)->get();

        return view('pages.dashboard.cr-eksternal.partials.history-card-body', [
            'externCr' => $externCr,
            'histories' => $histories,
            'truncateHint' => $totalCount > $limit,
            'emptyText' => 'Belum ada riwayat perubahan status.',
        ]);
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
