<?php

use App\Enums\ExternCrHistoryEvent;
use App\Models\ExternCrHistory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ExternCrHistory::query()
            ->where('event', ExternCrHistoryEvent::WaAuthorizationInviteDispatched->value)
            ->orderBy('id')
            ->each(function (ExternCrHistory $history): void {
                $properties = is_array($history->properties) ? $history->properties : [];
                $summary = ExternCrHistory::formatInviteDispatchedSummary($history);

                $recipientName = null;
                if (preg_match('/Permohonan otorisasi (?:gagal )?dikirim ke (.+)\.$/', $summary, $matches) === 1) {
                    $recipientName = trim($matches[1]);
                }

                if ($recipientName !== null && $recipientName !== '—') {
                    $properties['recipient_name'] = $recipientName;
                }

                $history->forceFill([
                    'summary' => $summary,
                    'properties' => $properties,
                ])->save();
            });
    }

    public function down(): void
    {
        // Data rewrite — tidak di-rollback.
    }
};
