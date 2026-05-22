<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExternCrStatus;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\ExternCr;
use App\Models\ExternCrApplication;
use App\Models\ExternCrAttachment;
use App\Models\ExternCrChangeReason;
use App\Support\DivisionMentionParser;
use App\Support\ExternCrNomorGenerator;
use App\Support\ExternCrPdfQr;
use App\Support\ExternCrPrintedPdfAssembler;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternCrController extends Controller
{
    private const ATTACH_ALLOWED_EXT = [
        'pdf', 'doc', 'docx', 'rar', 'zip', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp',
    ];

    private const ATTACH_PER_CR_MAX = 5;

    public function __construct(
        private readonly DivisionMentionParser $mentionParser,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $query = ExternCr::query()
            ->with(['division', 'application', 'changeReason'])
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

        return view('pages.dashboard.cr-eksternal.index', [
            'items' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.cr-eksternal.form', [
            'externCr' => null,
            'divisionsPemohon' => $this->activeDivisions(),
            'applications' => $this->activeApplications(),
            'changeReasons' => $this->activeReasons(),
            'divisionHints' => Division::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request, ExternCrNomorGenerator $nomorGenerator): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validateExternCrPayload($request, null);

        [$nomor, $seq] = $nomorGenerator->nextForDate($validated['tanggal']);

        $divisionIdsInvolved = $this->parseAndValidateDivisionMentions(
            $validated['divisions_terlibat_text'] ?? null,
            true
        );

        $cr = ExternCr::create([
            'nomor' => $nomor,
            'tanggal' => $validated['tanggal'],
            'daily_sequence' => $seq,
            'division_id' => (int) $validated['division_id'],
            'created_by_user_id' => auth()->id(),
            'bidang' => $validated['bidang'] ?? null,
            'nama' => $validated['nama'],
            'extern_cr_application_id' => (int) $validated['extern_cr_application_id'],
            'jenis_perubahan' => $validated['jenis_perubahan'],
            'extern_cr_change_reason_id' => (int) $validated['extern_cr_change_reason_id'],
            'kondisi_saat_ini' => $validated['kondisi_saat_ini'] ?? null,
            'perubahan_diharapkan' => $validated['perubahan_diharapkan'] ?? null,
            'risiko_bila_tidak' => $validated['risiko_bila_tidak'] ?? null,
            'prioritas' => $validated['prioritas'],
            'status' => $validated['status'],
            'deskripsi_permintaan' => $validated['deskripsi_permintaan'] ?? null,
            'divisions_terlibat_text' => $this->normalizedDivisionsTerlibatText($validated['divisions_terlibat_text'] ?? null),
        ]);

        $cr->divisionsInvolved()->sync($divisionIdsInvolved);

        $this->storeUploadedAttachments($cr, $request);

        return redirect()->route('admin.cr-eksternal.index')
            ->with('status', 'CR Eksternal '.$cr->nomor.' berhasil dibuat.');
    }

    public function edit(ExternCr $externCr): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $externCr->load(['attachments', 'divisionsInvolved']);

        return view('pages.dashboard.cr-eksternal.form', [
            'externCr' => $externCr,
            'divisionsPemohon' => $this->activeDivisions(),
            'applications' => $this->activeApplications(),
            'changeReasons' => $this->activeReasons(),
            'divisionHints' => Division::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, ExternCr $externCr): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validateExternCrPayload($request, $externCr);

        $divisionIdsInvolved = $this->parseAndValidateDivisionMentions(
            $validated['divisions_terlibat_text'] ?? null,
            false
        );

        $externCr->update([
            'tanggal' => $validated['tanggal'],
            'division_id' => (int) $validated['division_id'],
            'bidang' => $validated['bidang'] ?? null,
            'nama' => $validated['nama'],
            'extern_cr_application_id' => (int) $validated['extern_cr_application_id'],
            'jenis_perubahan' => $validated['jenis_perubahan'],
            'extern_cr_change_reason_id' => (int) $validated['extern_cr_change_reason_id'],
            'kondisi_saat_ini' => $validated['kondisi_saat_ini'] ?? null,
            'perubahan_diharapkan' => $validated['perubahan_diharapkan'] ?? null,
            'risiko_bila_tidak' => $validated['risiko_bila_tidak'] ?? null,
            'prioritas' => $validated['prioritas'],
            'status' => $validated['status'],
            'deskripsi_permintaan' => $validated['deskripsi_permintaan'] ?? null,
            'divisions_terlibat_text' => $this->normalizedDivisionsTerlibatText($validated['divisions_terlibat_text'] ?? null),
        ]);

        $externCr->divisionsInvolved()->sync($divisionIdsInvolved);

        $this->storeUploadedAttachments($externCr, $request);

        return redirect()->route('admin.cr-eksternal.index')
            ->with('status', 'CR Eksternal '.$externCr->nomor.' diperbarui.');
    }

    public function destroy(ExternCr $externCr): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $nomor = $externCr->nomor;
        $externCr->delete();

        return redirect()->route('admin.cr-eksternal.index')
            ->with('status', 'CR '.$nomor.' dihapus.');
    }

    public function updateStatus(Request $request, ExternCr $externCr): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ExternCrStatus::class)],
        ]);

        $status = ExternCrStatus::from($validated['status']);
        $externCr->update(['status' => $status]);

        return back()->with(
            'status',
            'Status CR '.$externCr->nomor.' diubah menjadi: '.$status->label().'.'
        );
    }

    public function printPdf(ExternCr $externCr): Response
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $externCr->load(['attachments', 'division', 'application', 'changeReason', 'creator', 'divisionsInvolved']);

        $reasonsForPdf = ExternCrChangeReason::query()
            ->where(function ($q) use ($externCr) {
                $q->where('is_active', true)
                    ->orWhere('id', $externCr->extern_cr_change_reason_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $logoPath = public_path('images/bkk.png');
        $logoDataUri = null;
        if (is_readable($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        $creatorSignedUrl = ExternCrPdfQr::signedVerifyUrl($externCr, ExternCrPdfQr::PURPOSE_CREATOR);
        $approverSignedUrl = ExternCrPdfQr::signedVerifyUrl($externCr, ExternCrPdfQr::PURPOSE_APPROVER);

        $divisiTerlibatDisplay = trim((string) ($externCr->divisions_terlibat_text ?? ''));
        if ($divisiTerlibatDisplay === '' && $externCr->divisionsInvolved->isNotEmpty()) {
            $divisiTerlibatDisplay = $externCr->divisionsInvolved->sortBy('name')->pluck('name')->implode(', ');
        }

        $pdf = Pdf::loadView('pages.dashboard.cr-eksternal.pdf-permintaan-perubahan', [
            'cr' => $externCr,
            'reasonsForPdf' => $reasonsForPdf,
            'logoDataUri' => $logoDataUri,
            'qrCreatorDataUri' => ExternCrPdfQr::dataUriForUrl($creatorSignedUrl),
            'qrApproverDataUri' => ExternCrPdfQr::dataUriForUrl($approverSignedUrl),
            'divisiTerlibatDisplay' => $divisiTerlibatDisplay !== '' ? $divisiTerlibatDisplay : '—',
        ])->setPaper('a4', 'portrait');

        $mainBinary = $pdf->output();

        $fileName = 'CR-'.$externCr->nomor.'.pdf';

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ];

        $sortedPdfAttachments = $externCr->attachments
            ->sortBy(fn (ExternCrAttachment $a) => [$a->position, $a->id]);

        $paths = [];
        foreach ($sortedPdfAttachments as $attachment) {
            if (! $this->attachmentIsPdfForMerge($attachment)) {
                continue;
            }

            try {
                $absolute = Storage::disk($attachment->disk)->path($attachment->path);
            } catch (\Throwable) {
                continue;
            }

            if (is_readable($absolute)) {
                $paths[] = $absolute;
            }
        }

        if ($paths === []) {
            return response($mainBinary, 200, $headers);
        }

        try {
            $merged = ExternCrPrintedPdfAssembler::mergeMainWithPdfAttachments($mainBinary, $paths);

            return response($merged, 200, $headers);
        } catch (\Throwable $e) {
            Log::warning('CR PDF: gagal gabung lampiran PDF, hanya formulir utama yang dikeluarkan.', [
                'extern_cr_id' => $externCr->id,
                'nomor' => $externCr->nomor,
                'message' => $e->getMessage(),
            ]);

            return response($mainBinary, 200, $headers);
        }
    }

    public function downloadAttachment(ExternCr $externCr, ExternCrAttachment $attachment): StreamedResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless($attachment->extern_cr_id === $externCr->id, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name ?? basename($attachment->path));
    }

    public function destroyAttachment(ExternCr $externCr, ExternCrAttachment $attachment): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless($attachment->extern_cr_id === $externCr->id, 404);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', 'Lampiran dihapus.');
    }

    private function attachmentIsPdfForMerge(ExternCrAttachment $attachment): bool
    {
        $name = $attachment->original_name ?: basename($attachment->path);
        if (Str::lower((string) pathinfo((string) $name, PATHINFO_EXTENSION)) === 'pdf') {
            return true;
        }

        return str_contains(Str::lower((string) ($attachment->mime ?? '')), 'pdf');
    }

    private function activeDivisions()
    {
        return Division::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function activeApplications()
    {
        return ExternCrApplication::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function activeReasons()
    {
        return ExternCrChangeReason::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function validateExternCrPayload(Request $request, ?ExternCr $existing): array
    {
        $strictActiveFk = $existing === null;

        $divisionRule = ['required', 'integer'];
        $divisionRule[] = $strictActiveFk
            ? Rule::exists('divisions', 'id')->where('is_active', true)
            : Rule::exists('divisions', 'id');

        $applicationRule = ['required', 'integer'];
        $applicationRule[] = $strictActiveFk
            ? Rule::exists('extern_cr_applications', 'id')->where('is_active', true)
            : Rule::exists('extern_cr_applications', 'id');

        $reasonRule = ['required', 'integer'];
        $reasonRule[] = $strictActiveFk
            ? Rule::exists('extern_cr_change_reasons', 'id')->where('is_active', true)
            : Rule::exists('extern_cr_change_reasons', 'id');

        $rules = [
            'tanggal' => ['required', 'date'],
            'division_id' => $divisionRule,
            'bidang' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'extern_cr_application_id' => $applicationRule,
            'jenis_perubahan' => ['required', 'string', 'in:temporary,permanent'],
            'extern_cr_change_reason_id' => $reasonRule,
            'kondisi_saat_ini' => ['nullable', 'string'],
            'perubahan_diharapkan' => ['nullable', 'string'],
            'risiko_bila_tidak' => ['nullable', 'string'],
            'prioritas' => ['required', 'string', 'in:rendah,sedang,tinggi'],
            'status' => ['required', Rule::enum(ExternCrStatus::class)],
            'deskripsi_permintaan' => ['nullable', 'string'],
            'divisions_terlibat_text' => ['nullable', 'string', 'max:20000'],
        ];

        $existingCount = $existing?->attachments()->count() ?? 0;

        $maxNew = max(0, self::ATTACH_PER_CR_MAX - $existingCount);
        $rules['attachments'] = ['nullable', 'array', 'max:'.$maxNew];
        $rules['attachments.*'] = [
            'file',
            'max:10240',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }
                $ext = Str::lower($value->getClientOriginalExtension());
                if (! in_array($ext, self::ATTACH_ALLOWED_EXT, true)) {
                    $fail(__('Ekstensi file tidak diizinkan. Diperbolehkan: :ext', ['ext' => implode(', ', self::ATTACH_ALLOWED_EXT)]));
                }
            },
        ];

        return $request->validate($rules);
    }

    private function normalizedDivisionsTerlibatText(?string $text): ?string
    {
        $t = trim((string) $text);

        return $t === '' ? null : $t;
    }

    /**
     * @return int[]
     */
    private function parseAndValidateDivisionMentions(?string $text, bool $strictActiveOnly): array
    {
        $pool = $this->divisionsForMentionMatching($strictActiveOnly);
        $parsed = $this->mentionParser->parse((string) $text, $pool);
        if ($parsed['unknown_mentions'] !== []) {
            throw ValidationException::withMessages([
                'divisions_terlibat_text' => [
                    'Nama setelah @ tidak cocok divisi manapun: «'.implode('», «', $parsed['unknown_mentions']).'». Tulis @ disertai nama persis seperti di master divisi (lihat petunjuk di form).',
                ],
            ]);
        }

        return $parsed['division_ids'];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Division>
     */
    private function divisionsForMentionMatching(bool $activeOnly)
    {
        $q = Division::query()->orderBy('name');

        if ($activeOnly) {
            $q->where('is_active', true);
        }

        return $q->get(['id', 'name']);
    }

    private function storeUploadedAttachments(ExternCr $cr, Request $request): void
    {
        $uploads = $request->file('attachments', []);

        $position = (int) $cr->attachments()->max('position');
        foreach ($uploads as $upload) {
            if (! $upload) {
                continue;
            }
            if ($cr->attachments()->count() >= self::ATTACH_PER_CR_MAX) {
                break;
            }

            $position++;
            $subdir = Str::slug($cr->nomor, '_').'/'.$cr->id;
            $path = $upload->store("extern_cr/{$subdir}", 'public');

            ExternCrAttachment::create([
                'extern_cr_id' => $cr->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime' => $upload->getClientMimeType(),
                'size_bytes' => $upload->getSize() ?: null,
                'position' => $position,
            ]);
        }
    }
}
