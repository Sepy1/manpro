<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $cr = $outcome['extern_cr'] ?? null;
        $result = $outcome['result'] ?? '';
        $existing = $outcome['existing_decision'] ?? null;
    @endphp
    <title>
        @if ($cr)
            Otorisasi CR — {{ $cr->nomor }}
        @else
            Otorisasi CR
        @endif
    </title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 36rem; margin: 2rem auto; padding: 0 1rem; color: #0f172a; }
        h1 { font-size: 1.15rem; margin: 0 0 .5rem 0; }
        .ok { border: 1px solid #16a34a; background: #f0fdf4; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        .pending { border: 1px solid #ca8a04; background: #fffbeb; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        .reject { border: 1px solid #dc2626; background: #fef2f2; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        .neutral { border: 1px solid #cbd5e1; background: #f8fafc; padding: 1rem 1.1rem; border-radius: 0.75rem; }
        dl { margin: 1rem 0; }
        dt { font-size: .75rem; text-transform: uppercase; color: #64748b; margin-top: .75rem; }
        dd { margin: .2rem 0 0 0; font-weight: 600; }
        p.small { font-size: .85rem; color: #64748b; }
    </style>
</head>
<body>
    @if ($result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_DISPATCH_NOT_FOUND)
        <div class="neutral">
            <h1>Tautan tidak valid</h1>
            <p>Tautan otorisasi tidak ditemukan atau sudah kedaluwarsa. Buka kembali pesan WhatsApp terbaru untuk CR ini.</p>
        </div>
    @elseif ($result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_USER_UNAUTHORIZED)
        <div class="neutral">
            <h1>Akses ditolak</h1>
            <p>Akun otorisator tidak lagi berhak memberi keputusan untuk CR eksternal.</p>
        </div>
    @elseif ($cr)
        @if ($result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_APPLIED)
            @if ($requestedDecision === \App\Models\ExternCr::WA_AUTH_APPROVED)
                <div class="ok">
                    <h1>CR disetujui</h1>
                    <p>Keputusan <strong>setuju</strong> untuk CR <strong>{{ $cr->nomor }}</strong> telah tercatat.</p>
                </div>
            @else
                <div class="reject">
                    <h1>CR ditolak</h1>
                    <p>Keputusan <strong>tolak</strong> untuk CR <strong>{{ $cr->nomor }}</strong> telah tercatat.</p>
                </div>
            @endif
        @else
            @if ($existing === \App\Models\ExternCr::WA_AUTH_APPROVED)
                <div class="ok">
                    <h1>Sudah disetujui sebelumnya</h1>
                    <p>CR <strong>{{ $cr->nomor }}</strong> sudah pernah <strong>disetujui</strong>. Keputusan pertama tetap berlaku.</p>
                </div>
            @elseif ($existing === \App\Models\ExternCr::WA_AUTH_REJECTED)
                <div class="reject">
                    <h1>Sudah ditolak sebelumnya</h1>
                    <p>CR <strong>{{ $cr->nomor }}</strong> sudah pernah <strong>ditolak</strong>. Keputusan pertama tetap berlaku.</p>
                </div>
            @else
                <div class="pending">
                    <h1>Keputusan sudah ada</h1>
                    <p>CR <strong>{{ $cr->nomor }}</strong> sudah memiliki keputusan otorisasi.</p>
                </div>
            @endif
        @endif

        <dl>
            <dt>Nomor</dt>
            <dd>{{ $cr->nomor }}</dd>
            <dt>Nama / judul</dt>
            <dd>{{ $cr->nama ?? '—' }}</dd>
            <dt>Penanggung jawab keputusan</dt>
            <dd>{{ $cr->authorizationResponder?->name ?? '—' }}</dd>
            <dt>Waktu keputusan</dt>
            <dd>{{ optional($cr->wa_authorization_at)->format('d/m/Y H:i') ?? '—' }}</dd>
        </dl>
        <p class="small">Anda dapat menutup halaman ini.</p>
    @endif
</body>
</html>
