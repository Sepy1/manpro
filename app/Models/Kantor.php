<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kantor extends Model
{
    protected $fillable = [
        'kode_kantor',
        'nama_kantor',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kantor_id');
    }

    /** Unit kantor kas milik cabang ini (banyak ke satu). */
    public function kasKantor(): HasMany
    {
        return $this->hasMany(KasKantor::class, 'kantor_id')->orderBy('kode_kas');
    }
}
