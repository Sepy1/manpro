@if ($truncateHint ?? false)
    <p class="mb-3 text-xs text-slate-600 dark:text-slate-400">
        Menampilkan {{ $histories->count() }} aktivitas terbaru dari riwayat ini.
    </p>
@endif
@include('pages.dashboard.cr-eksternal.partials.history-timeline', ['histories' => $histories, 'emptyText' => $emptyText ?? null])
