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
        'latitude',
        'longitude',
        'wireless_ip',
        'ping_status',
        'ping_latency_ms',
        'ping_checked_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'ping_latency_ms' => 'integer',
            'ping_checked_at' => 'datetime',
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
