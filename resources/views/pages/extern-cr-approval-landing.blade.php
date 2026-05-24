<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tindak Lanjut CR — {{ $cr->nomor }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 36rem; margin: 2rem auto; padding: 0 1rem; color: #0f172a; }
        h1 { font-size: 1.2rem; margin: 0 0 .75rem 0; }
        .card { border: 1px solid #cbd5e1; background: #f8fafc; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        dl { margin: 1rem 0; }
        dt { font-size: .75rem; text-transform: uppercase; color: #64748b; margin-top: .75rem; }
        dd { margin: .2rem 0 0 0; font-weight: 600; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.25rem; }
        .btn { display: inline-block; padding: .65rem 1rem; border-radius: .5rem; text-decoration: none; font-weight: 600; font-size: .95rem; }
        .btn-approve { background: #16a34a; color: #fff; }
        .btn-reject { background: #dc2626; color: #fff; }
        .btn-pdf { background: #2563eb; color: #fff; }
        p.small { font-size: .85rem; color: #64748b; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Tindak lanjut otorisasi Change Request</h1>
        <p>Silakan tinjau detail CR berikut, unduh PDF bila perlu, lalu pilih keputusan Anda.</p>
    </div>

    <dl>
        <dt>Nomor CR</dt>
        <dd>{{ $cr->nomor }}</dd>
        <dt>Nama Change Request</dt>
        <dd>{{ $cr->nama ?? '—' }}</dd>
        <dt>Pembuat</dt>
        <dd>{{ $cr->creator?->name ?? '—' }}</dd>
        <dt>Deskripsi perubahan</dt>
        <dd>{{ $cr->deskripsi_permintaan ?: ($cr->perubahan_diharapkan ?: '—') }}</dd>
    </dl>

    <div class="actions">
        <a class="btn btn-pdf" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Unduh PDF</a>
        @if (($cr->wa_authorization_decision ?? null) === null)
            <a class="btn btn-approve" href="{{ $approveUrl }}">Setujui</a>
            <a class="btn btn-reject" href="{{ $rejectUrl }}">Tolak</a>
        @endif
    </div>

    @if (($cr->wa_authorization_decision ?? null) !== null)
        <p class="small">CR ini sudah memiliki keputusan otorisasi ({{ $cr->wa_authorization_decision }}).</p>
    @else
        <p class="small">Keputusan pertama yang dicatat akan menjadi keputusan resmi.</p>
    @endif
</body>
</html>
