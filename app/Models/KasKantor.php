<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kantor kas (unit kas) untuk satu cabang / kantor induk ({@see Kantor}).
 *
 * Satu cabang bisa memiliki banyak baris pada tabel ini.
 */
class KasKantor extends Model
{
    protected $table = 'kas_kantor';

    protected $fillable = [
        'kantor_id',
        'kode_kas',
        'nama_kas',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    /** Label pendek untuk filter / cetak */
    public function label(): string
    {
        $nama = trim((string) ($this->nama_kas ?? ''));
        if ($nama !== '') {
            return $this->kode_kas.' — '.$nama;
        }

        return $this->kode_kas;
    }
}
