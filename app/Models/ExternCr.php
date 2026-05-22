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
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => ExternCrStatus::class,
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
}
