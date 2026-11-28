<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Log Security</title>
    <style>
        @page { margin: 24px 28px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 8px; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 3px; color: #0f172a; }
        .meta { color: #64748b; margin-bottom: 12px; }
        .filters { background: #f1f5f9; border: 1px solid #dbe3ed; border-radius: 5px; padding: 7px 9px; margin-bottom: 12px; }
        .filters strong { color: #334155; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #0f4c81; color: #fff; padding: 6px 4px; text-align: left; font-size: 7px; text-transform: uppercase; }
        td { border-bottom: 1px solid #dbe3ed; padding: 5px 4px; vertical-align: top; word-wrap: break-word; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .muted { color: #64748b; font-size: 7px; }
        .empty { padding: 28px; text-align: center; color: #64748b; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; text-align: right; color: #64748b; font-size: 7px; }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">Halaman <span class="page"></span></div>
    <h1>Log Security</h1>
    <div class="meta">Dibuat {{ $generatedAt->format('d/m/Y H:i:s') }} WIB - {{ number_format($logs->count()) }} data</div>
    @php
        $activeFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '');
    @endphp
    <div class="filters">
        <strong>Filter:</strong>
        {{ $activeFilters->isEmpty() ? 'Semua data' : $activeFilters->map(fn ($value, $key) => str_replace('_', ' ', ucfirst($key)).': '.$value)->implode(' | ') }}
    </div>
    <table>
        <thead><tr>
            <th style="width:11%">Waktu</th><th style="width:10%">Device</th><th style="width:7%">Severity</th>
            <th style="width:16%">Serangan</th><th style="width:9%">Action</th><th style="width:13%">Source</th>
            <th style="width:13%">Destination</th><th style="width:21%">Request</th>
        </tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->event_time }}</td>
                <td>{{ $log->device }}</td>
                <td>{{ $log->severity }}</td>
                <td>{{ $log->attack }}<div class="muted">ID {{ $log->attackid }} - {{ $log->subtype }}</div></td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->srcip }}:{{ $log->srcport }}</td>
                <td>{{ $log->dstip }}:{{ $log->dstport }}<div class="muted">{{ $log->service }}</div></td>
                <td>{{ $log->method !== '-' ? $log->method.' ' : '' }}{{ $log->url }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Tidak ada log yang sesuai dengan filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
