<?php

namespace App\Support;

use App\Enums\ExternCrHistoryEvent;
use App\Enums\ExternCrStatus;
use App\Models\Division;
use App\Models\ExternCr;
use App\Models\ExternCrApplication;
use App\Models\ExternCrChangeReason;
use App\Models\ExternCrHistory;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ExternCrHistoryRecorder
{
    /**
     * Kolom utama yang dibandingkan ketika formulir penyimpanan/ubah.
     *
     * @var list<string>
     */
    private const TRACKED_ATTRIBUTES = [
        'tanggal',
        'division_id',
        'bidang',
        'nama',
        'extern_cr_application_id',
        'jenis_perubahan',
        'extern_cr_change_reason_id',
        'kondisi_saat_ini',
        'perubahan_diharapkan',
        'risiko_bila_tidak',
        'prioritas',
        'status',
        'deskripsi_permintaan',
        'divisions_terlibat_text',
    ];

    public static function created(ExternCr $cr): void
    {
        ExternCrHistory::query()->create([
            'extern_cr_id' => $cr->id,
            'user_id' => auth()->id(),
            'event' => ExternCrHistoryEvent::Created,
            'summary' => 'CR Eksternal dibuat.',
            'properties' => [
                'nomor' => $cr->nomor,
            ],
        ]);
    }

    public static function attachmentAdded(ExternCr $externCr, string $originalFilename): void
    {
        ExternCrHistory::query()->create([
            'extern_cr_id' => $externCr->id,
            'user_id' => auth()->id(),
            'event' => ExternCrHistoryEvent::AttachmentAdded,
            'summary' => 'Lampiran ditambahkan: '.$originalFilename.'.',
            'properties' => [
                'original_name' => $originalFilename,
            ],
        ]);
    }

    public static function attachmentRemoved(ExternCr $externCr, string $originalFilename): void
    {
        ExternCrHistory::query()->create([
            'extern_cr_id' => $externCr->id,
            'user_id' => auth()->id(),
            'event' => ExternCrHistoryEvent::AttachmentDeleted,
            'summary' => 'Lampiran dihapus: '.$originalFilename.'.',
            'properties' => [
                'original_name' => $originalFilename,
            ],
        ]);
    }

    public static function whatsappAuthorization(
        ExternCr $cr,
        int $actorUserId,
        string $decision,
        string $auditReference,
        ?string $rejectReason = null,
    ): void {
        $actorName = User::query()->whereKey($actorUserId)->value('name') ?? '—';
        $viaLabel = self::authorizationViaLabel($auditReference);

        $summary = match ($decision) {
            ExternCr::WA_AUTH_APPROVED => 'CR disetujui oleh '.$actorName.' melalui '.$viaLabel.'.',
            ExternCr::WA_AUTH_REJECTED => 'CR ditolak oleh '.$actorName.' melalui '.$viaLabel.'.',
            default => 'Keputusan otorisasi CR oleh '.$actorName.' melalui '.$viaLabel.'.',
        };

        if ($decision === ExternCr::WA_AUTH_REJECTED && $rejectReason !== null && trim($rejectReason) !== '') {
            $snip = preg_replace('/\s+/', ' ', trim($rejectReason)) ?? '';
            $summary .= ' Alasan: '.\Illuminate\Support\Str::limit($snip, 200, '…');
        }

        $verb = match ($decision) {
            ExternCr::WA_AUTH_APPROVED => 'Disetujui',
            ExternCr::WA_AUTH_REJECTED => 'Ditolak',
            default => $decision,
        };

        $properties = [
            'decision' => $decision,
            'decision_label' => $verb,
            'audit_reference' => $auditReference,
            'flow' => $viaLabel,
            'actor_name' => $actorName,
            'channel' => 'whatsapp',
        ];

        if ($decision === ExternCr::WA_AUTH_REJECTED && $rejectReason !== null && trim($rejectReason) !== '') {
            $properties['reject_reason'] = trim($rejectReason);
        }

        ExternCrHistory::query()->create([
            'extern_cr_id' => $cr->id,
            'user_id' => $actorUserId,
            'event' => ExternCrHistoryEvent::WhatsappAuthorization,
            'summary' => $summary,
            'properties' => $properties,
        ]);
    }

    public static function waAuthorizationReset(ExternCr $cr, int $actorUserId, ?string $previousDecision): void
    {
        $prevLabel = match ($previousDecision) {
            ExternCr::WA_AUTH_APPROVED => 'disetujui',
            ExternCr::WA_AUTH_REJECTED => 'ditolak',
            default => 'belum diketahui',
        };

        ExternCrHistory::query()->create([
            'extern_cr_id' => $cr->id,
            'user_id' => $actorUserId,
            'event' => ExternCrHistoryEvent::WaAuthorizationReset,
            'summary' => 'Keputusan otorisasi WA direset (sebelumnya '.$prevLabel.') untuk otorisasi ulang.',
            'properties' => [
                'previous_decision' => $previousDecision,
            ],
        ]);
    }

    private static function authorizationViaLabel(string $auditReference): string
    {
        return match ($auditReference) {
            'approval-link-approve' => '2FA WhatsApp',
            'approval-link-reject' => 'halaman otorisasi WhatsApp',
            'link-approve', 'link-reject' => 'tautan WhatsApp',
            default => $auditReference,
        };
    }

    /** Admin memicu kirim ulang/notifikasi template otorisasi ke otorisator WhatsApp. */
    public static function waAuthorizationInviteDispatched(
        ExternCr $cr,
        ?int $actorUserId,
        int $successCount,
        int $recipientUserId,
    ): void {
        $recipientName = User::query()->whereKey($recipientUserId)->value('name') ?? '—';

        $summary = $successCount > 0
            ? 'Permohonan otorisasi dikirim ke '.$recipientName.'.'
            : 'Permohonan otorisasi gagal dikirim ke '.$recipientName.'.';

        ExternCrHistory::query()->create([
            'extern_cr_id' => $cr->id,
            'user_id' => $actorUserId,
            'event' => ExternCrHistoryEvent::WaAuthorizationInviteDispatched,
            'summary' => $summary,
            'properties' => [
                'success_count' => $successCount,
                'recipient_user_id' => $recipientUserId,
                'recipient_name' => $recipientName,
            ],
        ]);
    }

    /**
     * Mencatat perubahan status dari modal atau alur khusus (bukan penyimpanan form penuh).
     */
    public static function statusChanged(ExternCr $cr, ExternCrStatus $from, ExternCrStatus $to, ?string $note): void
    {
        if ($from === $to) {
            return;
        }

        $summary = 'Status '.$from->label().' → '.$to->label().'.';
        if ($note !== null && $note !== '') {
            $snip = preg_replace('/\s+/', ' ', trim($note)) ?? '';
            $summary .= ' Catatan: '.\Illuminate\Support\Str::limit($snip, 200, '…');
        }

        $properties = [
            'from' => $from->value,
            'from_label' => $from->label(),
            'to' => $to->value,
            'to_label' => $to->label(),
        ];

        if ($note !== null && $note !== '') {
            $properties['note'] = $note;
        }

        ExternCrHistory::query()->create([
            'extern_cr_id' => $cr->id,
            'user_id' => auth()->id(),
            'event' => ExternCrHistoryEvent::StatusChanged,
            'summary' => $summary,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  int[]  $divisionIdsAfterSync
     */
    public static function recordFormUpdateIfChanged(
        ExternCr $crBeforePersist,
        array $validated,
        array $divisionIdsAfterSync,
    ): void {
        $crBeforePersist->loadMissing(['divisionsInvolved']);

        $beforeDivisionIds = $crBeforePersist->divisionsInvolved
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $afterDivisionIds = collect($divisionIdsAfterSync)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        /** @var ExternCrStatus $newStatusFromForm */
        $newStatusFromForm = $validated['status'];
        $newStatus = $newStatusFromForm instanceof ExternCrStatus
            ? $newStatusFromForm
            : ExternCrStatus::tryFrom((string) $newStatusFromForm) ?? ExternCrStatus::Open;

        $changes = [];

        foreach (self::TRACKED_ATTRIBUTES as $attr) {
            $oldDisplayed = self::normalizeBeforeAttribute($crBeforePersist, $attr);
            $newDisplayed = self::normalizeAfterValidated($validated, $attr, $newStatus);

            if ($oldDisplayed !== $newDisplayed) {
                $changes[] = [
                    'attribute' => $attr,
                    'label' => self::attributeLabel($attr),
                    'was' => $oldDisplayed,
                    'now' => $newDisplayed,
                ];
            }
        }

        $divisionChange = [];
        if ($beforeDivisionIds !== $afterDivisionIds) {
            $divisionChange[] = [
                'attribute' => 'divisi_terlibat',
                'label' => 'Divisi terlibat (@)',
                'was' => self::divisionIdsToLabel($beforeDivisionIds),
                'now' => self::divisionIdsToLabel($afterDivisionIds),
            ];
        }

        if ($changes === [] && $divisionChange === []) {
            return;
        }

        $all = array_merge($changes, $divisionChange);

        ExternCrHistory::query()->create([
            'extern_cr_id' => $crBeforePersist->id,
            'user_id' => auth()->id(),
            'event' => ExternCrHistoryEvent::Updated,
            'summary' => self::buildSummary($all),
            'properties' => [
                'changes' => $all,
            ],
        ]);
    }

    private static function normalizeBeforeAttribute(ExternCr $cr, string $attr): string
    {
        $raw = $cr->getRawOriginal($attr);

        return match ($attr) {
            'tanggal' => self::formatDateValue($raw),
            'division_id' => self::divisionNameTyped((int) $raw),
            'extern_cr_application_id' => self::applicationNameTyped((int) $raw),
            'extern_cr_change_reason_id' => self::reasonNameTyped((int) $raw),
            'jenis_perubahan' => self::formatJenisPerubahan((string) $raw),
            'prioritas' => self::formatPrioritas((string) $raw),
            'status' => ExternCrStatus::from((string) $raw)->label(),
            default => self::flattenText((string) ($raw ?? '')),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function normalizeAfterValidated(array $validated, string $attr, ExternCrStatus $status): string
    {
        if ($attr === 'status') {
            return $status->label();
        }

        $value = array_key_exists($attr, $validated) ? $validated[$attr] : null;

        return match ($attr) {
            'tanggal' => Carbon::parse((string) $value)->toDateString(),
            'division_id' => self::divisionNameTyped((int) $value),
            'extern_cr_application_id' => self::applicationNameTyped((int) $value),
            'extern_cr_change_reason_id' => self::reasonNameTyped((int) $value),
            'jenis_perubahan' => self::formatJenisPerubahan((string) ($value ?? '')),
            'prioritas' => self::formatPrioritas((string) ($value ?? '')),
            default => self::flattenText($value === null ? '' : (string) $value),
        };
    }

    /**
     * @param  mixed  $raw
     */
    private static function formatDateValue($raw): string
    {
        if ($raw instanceof CarbonInterface) {
            return $raw->toDateString();
        }

        if ($raw === null || $raw === '') {
            return '';
        }

        return Carbon::parse((string) $raw)->toDateString();
    }

    /**
     * @param  array<int, mixed>  $changes
     */
    private static function buildSummary(array $changes): string
    {
        if ($changes === []) {
            return 'Data CR diperbarui.';
        }

        $parts = collect($changes)
            ->take(3)
            ->map(function (array $row) {
                $snip = fn (string $s) => \Illuminate\Support\Str::limit(
                    preg_replace('/\s+/', ' ', trim($s)) ?? '',
                    48,
                    '…'
                );

                return $row['label'].': '.$snip((string) $row['was']).' → '.$snip((string) $row['now']);
            })
            ->all();

        return implode('; ', $parts).(count($changes) > 3 ? '; …' : '.');
    }

    private static function attributeLabel(string $attr): string
    {
        return match ($attr) {
            'tanggal' => 'Tanggal dokumen',
            'division_id' => 'Divisi pemohon',
            'bidang' => 'Bidang',
            'nama' => 'Nama CR',
            'extern_cr_application_id' => 'Aplikasi / sistem',
            'jenis_perubahan' => 'Jenis perubahan',
            'extern_cr_change_reason_id' => 'Alasan perubahan',
            'kondisi_saat_ini' => 'Kondisi saat ini',
            'perubahan_diharapkan' => 'Perubahan diharapkan',
            'risiko_bila_tidak' => 'Risiko bila tidak dilakukan',
            'prioritas' => 'Prioritas',
            'status' => 'Status',
            'deskripsi_permintaan' => 'Deskripsi permintaan',
            'divisions_terlibat_text' => 'Teks divisi terlibat',
            default => $attr,
        };
    }

    private static function divisionNameTyped(int $id): string
    {
        return Division::query()->whereKey($id)->value('name') ?? ('#'.$id);
    }

    private static function applicationNameTyped(int $id): string
    {
        return ExternCrApplication::query()->whereKey($id)->value('name') ?? ('#'.$id);
    }

    private static function reasonNameTyped(int $id): string
    {
        return ExternCrChangeReason::query()->whereKey($id)->value('name') ?? ('#'.$id);
    }

    /**
     * @param  list<int|string>  $ids
     */
    private static function divisionIdsToLabel(array $ids): string
    {
        if ($ids === []) {
            return '—';
        }

        $names = Division::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names !== [] ? implode(', ', $names) : '—';
    }

    private static function formatJenisPerubahan(string $value): string
    {
        return match ($value) {
            'temporary' => 'Sementara',
            'permanent' => 'Permanen',
            default => $value,
        };
    }

    private static function formatPrioritas(string $value): string
    {
        return match ($value) {
            'rendah' => 'Rendah',
            'sedang' => 'Sedang',
            'tinggi' => 'Tinggi',
            default => $value,
        };
    }

    private static function flattenText(string $text): string
    {
        $trim = preg_replace('/\s+/', ' ', trim($text));

        return $trim ?? '';
    }
}
