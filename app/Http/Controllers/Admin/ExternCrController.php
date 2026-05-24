<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExternCrHistoryEvent;
use App\Enums\ExternCrStatus;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\ExternCr;
use App\Models\ExternCrApplication;
use App\Models\ExternCrAttachment;
use App\Models\ExternCrChangeReason;
use App\Models\ExternCrHistory;
use App\Models\User;
use App\Support\DivisionMentionParser;
use App\Support\ExternCrHistoryRecorder;
use App\Support\ExternCrMergedPdfBuilder;
use App\Support\ExternCrNomorGenerator;
use App\Support\IndonesianWhatsappPhoneNormalizer;
use App\Support\MahadataWhatsappExternCrAuthorizationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

        ExternCrHistoryRecorder::created($cr);
        $this->storeUploadedAttachments($cr, $request);

        try {
            app(MahadataWhatsappExternCrAuthorizationNotifier::class)->notifyAuthorizersAboutNewCr($cr);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi WA otorisasi CR eksternal gagal.', [
                'extern_cr_id' => $cr->id,
                'message' => $e->getMessage(),
            ]);
        }

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

        ExternCrHistoryRecorder::recordFormUpdateIfChanged($externCr, $validated, $divisionIdsInvolved);

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

    public function authorizersPayload(Request $request, ExternCr $externCr): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $reauthorize = $request->boolean('reauthorize');

        if ($externCr->hasWaAuthorizationDecision() && ! $reauthorize) {
            return response()->json([
                'wa_decision_locked' => true,
                'message' => 'CR ini sudah memiliki keputusan otorisasi WhatsApp.',
                'cr_id' => $externCr->id,
                'cr_nomor' => $externCr->nomor,
                'authorizers' => [],
            ], 423);
        }

        return response()->json([
            'wa_decision_locked' => false,
            'reauthorize' => $reauthorize,
            'cr_id' => $externCr->id,
            'cr_nomor' => $externCr->nomor,
            'authorizers' => $this->buildAuthorizersChecklistPayload($externCr, $reauthorize),
        ]);
    }

    public function sendWhatsappAuthorization(Request $request, ExternCr $externCr): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $reauthorize = $request->boolean('reauthorize');

        if ($externCr->hasWaAuthorizationDecision() && ! $reauthorize) {
            return back()->with('flash_error', 'CR ini sudah diotorisasi lewat WhatsApp; gunakan Otorisasi ulang.');
        }

        $validated = Validator::make($request->all(), [
            'authorizer_id' => ['required', 'integer'],
            'reauthorize' => ['sometimes', 'boolean'],
        ]);

        if ($validated->fails()) {
            return back()->with('flash_error', 'Pilih tepat satu otorisator yang akan menerima WhatsApp.');
        }

        if ($reauthorize && $externCr->hasWaAuthorizationDecision()) {
            $this->resetWaAuthorizationForReauthorize($externCr);
            $externCr->refresh();
        }

        $chosenId = (int) ($validated->validated()['authorizer_id'] ?? 0);

        $endpoint = trim((string) config('services.mahadata_whatsapp.endpoint'));
        $token = trim((string) config('services.mahadata_whatsapp.token'));
        $template = trim((string) config('services.mahadata_whatsapp.cr_authorization_template_name'));

        if ($endpoint === '' || $token === '' || $template === '') {
            return back()->with('flash_error', 'Konfigurasi Mahadata untuk otorisasi CR belum lengkap (endpoint pesan WhatsApp, token, dan nama template otorisasi).');
        }

        $allowedReceiverIds = $this->eligibleAuthorizerReceiverUserIds($externCr, $reauthorize);
        $sendIds = in_array($chosenId, $allowedReceiverIds, true) ? [$chosenId] : [];

        if ($sendIds === []) {
            return back()->with('flash_error', 'Pilihan otorisator tidak valid: harus satu pengguna dengan WA valid yang belum memberi keputusan untuk CR ini.');
        }

        try {
            $success = app(MahadataWhatsappExternCrAuthorizationNotifier::class)->notifyAuthorizersOnDemand($externCr, $sendIds);
        } catch (\Throwable $e) {
            Log::warning('Kirim WA otorisasi CR eksternal (manual): exception.', [
                'extern_cr_id' => $externCr->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('flash_error', 'Pengiriman WA gagal: silakan periksa log aplikasi atau coba lagi.');
        }

        $targetCount = 1;
        ExternCrHistoryRecorder::waAuthorizationInviteDispatched($externCr, auth()->id(), $success, $targetCount);

        if ($success === 0) {
            return back()->with(
                'flash_error',
                'WhatsApp tidak terkirim ke otorisator terpilih. Periksa template Mahadata, nomor pengguna, dan `storage/logs/laravel.log` (cari Mahadata CR auth WA). '
                .'Opsi: `MAHADATA_WHATSAPP_CR_AUTH_INCLUDE_QUICK_REPLY_COMPONENTS` / `MAHADATA_WHATSAPP_CR_AUTH_ACCEPT_PROXY_MESSAGE_IDS` di `.env` lalu `php artisan config:clear`.'
            );
        }

        return back()->with('status', $reauthorize
            ? "Otorisasi ulang: undangan WA terkirim ke 1 otorisator untuk {$externCr->nomor}."
            : "Undangan otorisasi WA terkirim ke 1 otorisator untuk {$externCr->nomor}.");
    }

    private function resetWaAuthorizationForReauthorize(ExternCr $externCr): void
    {
        $previous = $externCr->wa_authorization_decision;

        $externCr->forceFill([
            'wa_authorization_decision' => null,
            'wa_authorization_at' => null,
            'wa_authorization_by_user_id' => null,
            'wa_authorization_reject_reason' => null,
        ]);
        $externCr->save();

        ExternCrHistoryRecorder::waAuthorizationReset($externCr, (int) auth()->id(), $previous);
    }

    public function detailModalFragment(ExternCr $externCr): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $externCr->load(['application', 'changeReason', 'attachments']);

        $latestStatusChangeNote = $this->latestStatusChangeNoteForCurrentStatus($externCr);

        return view('pages.dashboard.cr-eksternal.partials.detail-modal-body', [
            'cr' => $externCr,
            'latestStatusChangeNote' => $latestStatusChangeNote,
        ]);
    }

    public function updateStatus(Request $request, ExternCr $externCr): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::enum(ExternCrStatus::class)],
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

        $rawStatus = $validated['status'];
        $newStatus = $rawStatus instanceof ExternCrStatus
            ? $rawStatus
            : ExternCrStatus::from((string) $rawStatus);

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

    public function historyModalFragment(ExternCr $externCr): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $externCr->load(['division']);

        $limit = 60;
        $baseQuery = $externCr->histories()
            ->with(['user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $totalCount = (clone $baseQuery)->count();
        $histories = (clone $baseQuery)->limit($limit)->get();

        return view('pages.dashboard.cr-eksternal.partials.history-card-body', [
            'externCr' => $externCr,
            'histories' => $histories,
            'truncateHint' => $totalCount > $limit,
        ]);
    }

    public function printPdf(ExternCr $externCr): Response
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return ExternCrMergedPdfBuilder::streamedInlineResponse($externCr);
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

        $originalName = (string) ($attachment->original_name ?: basename((string) $attachment->path));

        Storage::disk($attachment->disk)->delete($attachment->path);
        ExternCrHistoryRecorder::attachmentRemoved($externCr, $originalName);
        $attachment->delete();

        return back()->with('status', 'Lampiran dihapus.');
    }

    /** Catatan dari riwayat pergantian status terakhir yang menghasilkan status CR saat ini. */
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

    /**
     * Pengguna yang sudah mencatat keputusan otorisasi WA untuk CR ini (riwayat + kolom responder).
     *
     * @return list<int>
     */
    private function whatsappRespondedAuthorizerUserIds(ExternCr $externCr): array
    {
        $fromHistory = ExternCrHistory::query()
            ->where('extern_cr_id', $externCr->id)
            ->where('event', ExternCrHistoryEvent::WhatsappAuthorization->value)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $byColumn = $externCr->wa_authorization_by_user_id !== null
            ? [(int) $externCr->wa_authorization_by_user_id]
            : [];

        return array_values(array_unique(array_merge($fromHistory, $byColumn)));
    }

    /** ID pengguna yang boleh menerima undangan lagi: otorisator, WA valid, belum memberi keputusan. */
    private function eligibleAuthorizerReceiverUserIds(ExternCr $externCr, bool $ignorePreviousResponses = false): array
    {
        $responded = $ignorePreviousResponses
            ? []
            : array_flip($this->whatsappRespondedAuthorizerUserIds($externCr));

        $users = User::query()
            ->where('can_authorize_extern_cr', true)
            ->whereIn('role', User::ROLES)
            ->whereNotNull('phone')
            ->get(['id', 'phone']);

        $ids = [];
        foreach ($users as $user) {
            if (isset($responded[(int) $user->id])) {
                continue;
            }
            if (IndonesianWhatsappPhoneNormalizer::toWaDigits62(trim((string) $user->phone)) === null) {
                continue;
            }
            $ids[] = (int) $user->id;
        }

        return array_values(array_unique($ids));
    }

    /** @return list<array<string, mixed>> */
    private function buildAuthorizersChecklistPayload(ExternCr $externCr, bool $ignorePreviousResponses = false): array
    {
        $respondedFlip = $ignorePreviousResponses
            ? []
            : array_flip($this->whatsappRespondedAuthorizerUserIds($externCr));

        $users = User::query()
            ->where('can_authorize_extern_cr', true)
            ->whereIn('role', User::ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'phone']);

        $rows = [];
        $firstSelectableChosen = false;

        foreach ($users as $user) {
            $phoneRaw = trim((string) ($user->phone ?? ''));
            $waDigits = IndonesianWhatsappPhoneNormalizer::toWaDigits62($phoneRaw);
            $alreadyResponded = isset($respondedFlip[(int) $user->id]);
            $waDigitsOk = $phoneRaw !== '' && $waDigits !== null;
            $selectable = $waDigitsOk && ! $alreadyResponded;

            $disabledReason = null;
            if ($alreadyResponded) {
                $disabledReason = 'Sudah memberi keputusan otorisasi';
            } elseif ($phoneRaw === '' || ! $waDigitsOk) {
                $disabledReason = 'Nomor WhatsApp tidak valid atau kosong';
            }

            $checkedDefault = false;
            if ($selectable && ! $firstSelectableChosen) {
                $checkedDefault = true;
                $firstSelectableChosen = true;
            }

            $rows[] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => (string) $user->role,
                'phone_hint' => $this->waPhoneHintForUi($phoneRaw),
                'selectable' => $selectable,
                'already_responded' => $alreadyResponded,
                'wa_valid' => $waDigits !== null && $phoneRaw !== '',
                'checked_default' => $checkedDefault,
                'disabled_reason' => $disabledReason,
            ];
        }

        return $rows;
    }

    /** Menyembunyikan sebagian digit untuk ditampilkan di UI checklist. */
    private function waPhoneHintForUi(string $phoneDigitsOrRaw): string
    {
        $d = preg_replace('/\D+/', '', $phoneDigitsOrRaw) ?? '';

        return match (true) {
            $d === '' => '—',
            strlen($d) < 8 => Str::limit($d, 6).'…',
            default => substr($d, 0, 4).str_repeat('•', strlen($d) - 7).substr($d, -3),
        };
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
            ExternCrHistoryRecorder::attachmentAdded($cr, $upload->getClientOriginalName());
        }
    }
}
