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
                @if (
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
