<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesan template otorisasi CR yang terkirim — dipakai menghubungkan balasan tombol (context.id) ke CR & user.
 */
class WhatsappCrAuthorizationDispatch extends Model
{
    protected $table = 'whatsapp_cr_authorization_dispatches';

    protected $fillable = [
        'extern_cr_id',
        'user_id',
        'interaction_token',
        'wam_id',
        'recipient_wa_id',
    ];

    public function externCr(): BelongsTo
    {
        return $this->belongsTo(ExternCr::class, 'extern_cr_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
