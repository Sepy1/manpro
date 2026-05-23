<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi CR — {{ $cr->nomor }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 36rem; margin: 2rem auto; padding: 0 1rem; color: #0f172a; }
        h1 { font-size: 1.15rem; }
        .ok { border: 1px solid #16a34a; background: #f0fdf4; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        .pending { border: 1px solid #ca8a04; background: #fffbeb; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        dl { margin: 1rem 0; }
        dt { font-size: .75rem; text-transform: uppercase; color: #64748b; margin-top: .75rem; }
        dd { margin: .2rem 0 0 0; font-weight: 600; }
        p.small { font-size: .85rem; color: #64748b; }
        .reject { border: 1px solid #dc2626; background: #fef2f2; padding: 1rem 1.1rem; border-radius: 0.75rem; }
    </style>
</head>
<body>
    @if ($purpose === 'creator')
        <div class="ok">
            <h1>Tanda tangan elektronik (pembuat)</h1>
            <p>Dokumen <strong>{{ $cr->nomor }}</strong> tercatat dalam sistem sebagai permintaan perubahan dengan data berikut:</p>
        </div>
        <dl>
            <dt>Nomor</dt>
            <dd>{{ $cr->nomor }}</dd>
            <dt>Nama / judul</dt>
            <dd>{{ $cr->nama ?? '—' }}</dd>
            <dt>Tanggal dokumen</dt>
            <dd>{{ $cr->tanggal?->format('d/m/Y') ?? '—' }}</dd>
            <dt>Divisi pemohon</dt>
            <dd>{{ $cr->division?->name ?? '—' }}</dd>
            <dt>Pembuat (akun aplikasi)</dt>
            <dd>{{ $cr->creator?->name ?? 'Tidak tercatat' }}</dd>
        </dl>
        <p class="small">Tautan ini hanya sah jika Anda membukanya langsung dari hasil pemindaian kode QR resmi pada PDF formulir.</p>
    @else
        @if (($cr->wa_authorization_decision ?? null) === \App\Models\ExternCr::WA_AUTH_APPROVED)
            <div class="ok">
                <h1>Persetujuan tercatat</h1>
                <p>CR <strong>{{ $cr->nomor }}</strong> telah <strong>disetujui</strong> melalui WhatsApp.</p>
            </div>
            <dl>
                <dt>Nama / judul</dt>
                <dd>{{ $cr->nama ?? '—' }}</dd>
                <dt>Penanggung jawab keputusan</dt>
                <dd>{{ $cr->authorizationResponder?->name ?? '—' }}</dd>
                <dt>Waktu keputusan</dt>
                <dd>{{ optional($cr->wa_authorization_at)->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        @elseif (($cr->wa_authorization_decision ?? null) === \App\Models\ExternCr::WA_AUTH_REJECTED)
            <div class="reject">
                <h1>Persetujuan ditolak</h1>
                <p>CR <strong>{{ $cr->nomor }}</strong> <strong>ditolak</strong> melalui WhatsApp.</p>
            </div>
            <dl>
                <dt>Nama / judul</dt>
                <dd>{{ $cr->nama ?? '—' }}</dd>
                <dt>Penanggung jawab keputusan</dt>
                <dd>{{ $cr->authorizationResponder?->name ?? '—' }}</dd>
                <dt>Waktu keputusan</dt>
                <dd>{{ optional($cr->wa_authorization_at)->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        @else
            <div class="pending">
                <h1>Verifikasi persetujuan</h1>
                <p>CR <strong>{{ $cr->nomor }}</strong>: <strong>Menunggu keputusan otorisator</strong> (misalnya lewat WhatsApp).</p>
                <p class="small">Jika Anda otorisator, gunakan tautan tombol pada pesan template atau balasan interaktif untuk menanggapi.</p>
            </div>
            <dl>
                <dt>Nomor</dt>
                <dd>{{ $cr->nomor }}</dd>
                <dt>Nama / judul</dt>
                <dd>{{ $cr->nama ?? '—' }}</dd>
                <dt>Tanggal dokumen</dt>
                <dd>{{ $cr->tanggal?->format('d/m/Y') ?? '—' }}</dd>
            </dl>
        @endif
</body>
</html>
