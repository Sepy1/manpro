<ul class="m-0 list-none space-y-0 p-0" role="list">
    @forelse ($histories as $row)
        @php
            $properties = $row->properties ?? [];
            $changes = isset($properties['changes']) && is_array($properties['changes']) ? $properties['changes'] : null;

            $accent = match ($row->event) {
                \App\Enums\ExternCrHistoryEvent::Created => 'border-emerald-500 bg-emerald-50 dark:border-emerald-500 dark:bg-emerald-950/30',
                \App\Enums\ExternCrHistoryEvent::Updated => 'border-sky-500 bg-sky-50 dark:border-sky-500 dark:bg-sky-950/30',
                \App\Enums\ExternCrHistoryEvent::StatusChanged => 'border-violet-500 bg-violet-50 dark:border-violet-500 dark:bg-violet-950/30',
                \App\Enums\ExternCrHistoryEvent::AttachmentAdded => 'border-amber-500 bg-amber-50 dark:border-amber-500 dark:bg-amber-950/30',
                \App\Enums\ExternCrHistoryEvent::AttachmentDeleted => 'border-rose-500 bg-rose-50 dark:border-rose-500 dark:bg-rose-950/30',
                \App\Enums\ExternCrHistoryEvent::WhatsappAuthorization => 'border-teal-500 bg-teal-50 dark:border-teal-500 dark:bg-teal-950/30',
                \App\Enums\ExternCrHistoryEvent::WaAuthorizationInviteDispatched => 'border-cyan-500 bg-cyan-50 dark:border-cyan-500 dark:bg-cyan-950/35',
                \App\Enums\ExternCrHistoryEvent::WaAuthorizationReset => 'border-orange-500 bg-orange-50 dark:border-orange-500 dark:bg-orange-950/30',
            };
        @endphp
        <li class="relative border-l border-gray-200 pl-6 pb-6 text-left last:border-l-0 dark:border-gray-700">
            <span class="absolute -left-[9px] top-1 inline-flex h-4 w-4 rounded-full border-2 border-gray-900 bg-gray-900 dark:border-white dark:bg-white" aria-hidden="true"></span>
            <div class="rounded-lg border px-4 py-3 shadow-sm {{ $accent }} dark:text-gray-50">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">
                        {{ $row->event->label() }}</span>
                    <span class="text-xs text-gray-600 dark:text-gray-300">{{ $row->created_at->format('d/m/Y H:i:s') }}</span>
                    <span class="text-xs text-gray-700 dark:text-gray-200">{{ $row->user?->name ?? '—' }}</span>
                </div>
                @if ($row->summary)
                    <p class="mt-2 text-sm text-gray-900 dark:text-white/95">{{ $row->summary }}</p>
                @endif
                @if ($row->event === \App\Enums\ExternCrHistoryEvent::WhatsappAuthorization)
                    @php
                        $decisionLabel = isset($properties['decision_label']) && is_string($properties['decision_label'])
                            ? $properties['decision_label']
                            : null;
                        $flowLabel = isset($properties['flow']) && is_string($properties['flow']) ? $properties['flow'] : null;
                    @endphp
                    @if ($decisionLabel || $flowLabel)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($decisionLabel)
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide',
                                    'border border-emerald-300 bg-emerald-100 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-100' => ($properties['decision'] ?? '') === \App\Models\ExternCr::WA_AUTH_APPROVED,
                                    'border border-rose-300 bg-rose-100 text-rose-900 dark:border-rose-700 dark:bg-rose-950/50 dark:text-rose-100' => ($properties['decision'] ?? '') === \App\Models\ExternCr::WA_AUTH_REJECTED,
                                    'border border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100' => ! in_array($properties['decision'] ?? '', [\App\Models\ExternCr::WA_AUTH_APPROVED, \App\Models\ExternCr::WA_AUTH_REJECTED], true),
                                ])>{{ $decisionLabel }}</span>
                            @endif
                            @if ($flowLabel)
                                <span class="inline-flex rounded-md border border-slate-300 bg-white/70 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-200">{{ $flowLabel }}</span>
                            @endif
                        </div>
                    @endif
                @endif
                @if (
                    ($row->event === \App\Enums\ExternCrHistoryEvent::StatusChanged
                        || $row->event === \App\Enums\ExternCrHistoryEvent::WhatsappAuthorization)
                    && isset($properties['reject_reason']) && is_string($properties['reject_reason']) && trim($properties['reject_reason']) !== ''
                )
                    <div class="mt-2 whitespace-pre-wrap rounded-md border border-rose-200/80 bg-white/70 px-3 py-2 text-xs leading-relaxed text-gray-900 dark:border-rose-800/70 dark:bg-slate-900/80 dark:text-gray-100">
                        <span class="font-semibold">Alasan penolakan:</span> {{ $properties['reject_reason'] }}
                    </div>
                @elseif (
                    $row->event === \App\Enums\ExternCrHistoryEvent::StatusChanged
                    && isset($properties['note']) && is_string($properties['note']) && trim($properties['note']) !== ''
                )
                    <div class="mt-2 whitespace-pre-wrap rounded-md border border-violet-200/80 bg-white/70 px-3 py-2 text-xs leading-relaxed text-gray-900 dark:border-violet-800/70 dark:bg-slate-900/80 dark:text-gray-100">{{ $properties['note'] }}</div>
                @endif
                @if (isset($changes) && $changes !== [])
                    <details class="mt-2 text-xs text-gray-800 dark:text-gray-100 [&_summary]:cursor-pointer [&_summary]:font-medium [&_summary]:text-gray-700 dark:[&_summary]:text-gray-200">
                        <summary>Rincian perubahan</summary>
                        <ul class="mt-2 list-disc space-y-1 pl-4 text-gray-800 dark:text-gray-100">
                            @foreach ($changes as $c)
                                <li><span class="font-medium">{{ $c['label'] ?? ($c['attribute'] ?? '—') }}</span>:
                                    {{ \Illuminate\Support\Str::limit($c['was'] ?? '', 200) }}
                                    →
                                    {{ \Illuminate\Support\Str::limit($c['now'] ?? '', 200) }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </li>
    @empty
        <li class="text-sm text-gray-500 dark:text-gray-400">
            {{ $emptyText ?? 'Belum ada entri riwayat.' }}
        </li>
    @endforelse
</ul>
