<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternCrAttachment extends Model
{
    protected $table = 'extern_cr_attachments';

    protected $fillable = [
        'extern_cr_id',
        'extern_cr_history_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'position',
    ];

    public function externCr(): BelongsTo
    {
        return $this->belongsTo(ExternCr::class, 'extern_cr_id');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(ExternCrHistory::class, 'extern_cr_history_id');
    }
}
