<?php

namespace App\Models;

use App\Enums\ExternCrHistoryEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternCrHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'extern_cr_id',
        'user_id',
        'event',
        'summary',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'event' => ExternCrHistoryEvent::class,
            'properties' => 'array',
        ];
    }

    public function externCr(): BelongsTo
    {
        return $this->belongsTo(ExternCr::class, 'extern_cr_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExternCrAttachment::class, 'extern_cr_history_id');
    }

    public function getDisplaySummaryAttribute(): string
    {
        if ($this->event === ExternCrHistoryEvent::WaAuthorizationInviteDispatched) {
            return self::formatInviteDispatchedSummary($this);
        }

        return (string) ($this->summary ?? '');
    }

    public static function formatInviteDispatchedSummary(self $history): string
    {
        $properties = is_array($history->properties) ? $history->properties : [];
        $recipientName = isset($properties['recipient_name']) && is_string($properties['recipient_name'])
            ? trim($properties['recipient_name'])
            : self::guessInviteRecipientName($history);

        if ($recipientName === '') {
            $recipientName = '—';
        }

        $success = (int) ($properties['success_count'] ?? 1) > 0;

        return $success
            ? 'Permohonan otorisasi dikirim ke '.$recipientName.'.'
            : 'Permohonan otorisasi gagal dikirim ke '.$recipientName.'.';
    }

    private static function guessInviteRecipientName(self $history): string
    {
        if ($history->created_at === null) {
            return '—';
        }

        $dispatch = WhatsappCrAuthorizationDispatch::query()
            ->with('user:id,name')
            ->where('extern_cr_id', $history->extern_cr_id)
            ->whereBetween('created_at', [
                $history->created_at->copy()->subMinutes(10),
                $history->created_at->copy()->addMinutes(10),
            ])
            ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, created_at, ?))', [$history->created_at])
            ->first();

        return trim((string) ($dispatch?->user?->name ?? ''));
    }
}
