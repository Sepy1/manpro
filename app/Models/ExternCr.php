<?php

namespace App\Models;

use App\Enums\ExternCrStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternCr extends Model
{
    protected $table = 'extern_crs';

    protected $fillable = [
        'nomor',
        'tanggal',
        'daily_sequence',
        'division_id',
        'created_by_user_id',
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
        'divisions_terlibat_text',
        'deskripsi_permintaan',
        'wa_authorization_decision',
        'wa_authorization_at',
        'wa_authorization_by_user_id',
        'wa_authorization_reject_reason',
    ];

    public const WA_AUTH_APPROVED = 'approved';

    public const WA_AUTH_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => ExternCrStatus::class,
            'wa_authorization_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ExternCr $cr) {
            foreach ($cr->attachments as $attachment) {
                \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete($attachment->path);
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(ExternCrApplication::class, 'extern_cr_application_id');
    }

    public function changeReason(): BelongsTo
    {
        return $this->belongsTo(ExternCrChangeReason::class, 'extern_cr_change_reason_id');
    }

    /** Divisi lain yang ikut serta (tagging multi). */
    public function divisionsInvolved(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'extern_cr_divisions', 'extern_cr_id', 'division_id')->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExternCrAttachment::class, 'extern_cr_id')->orderBy('position')->orderBy('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ExternCrHistory::class, 'extern_cr_id');
    }

    public function authorizationResponder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wa_authorization_by_user_id');
    }

    public function whatsappAuthorizationDispatches(): HasMany
    {
        return $this->hasMany(WhatsappCrAuthorizationDispatch::class, 'extern_cr_id');
    }

    /** Apakah keputusan otorisasi WA sudah tercatat (setuju atau tidak). */
    public function hasWaAuthorizationDecision(): bool
    {
        return $this->wa_authorization_decision !== null && $this->wa_authorization_decision !== '';
    }
}
