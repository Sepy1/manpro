<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Permintaan Perubahan {{ $cr->nomor }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.35;
        }
        .sheet { width: 100%; }
        table.bound { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.bound col.label { width: 32%; }
        table.bound col.value { width: 68%; }
        table.bound td,
        table.bound th {
            border: 1px solid #222;
            padding: 5px 6px;
            vertical-align: top;
        }
        .no-border-table { border-collapse: collapse; width: 100%; }
        .no-border-table td { padding: 0; vertical-align: top; border: none; }
        .header-company-name { font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .header-address { font-size: 10px; margin-top: 2px; }
        .hdr-box-title {
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 0.06em;
        }
        .hdr-box-lines { margin-top: 6px; font-size: 9.5px; }
        .section-gray {
            background: #d9d9d9;
            font-weight: bold;
            text-align: center;
            font-size: 9.5px;
        }
        .label-col { font-weight: bold; white-space: nowrap; width: 32%; }
        .cb { font-family: DejaVu Sans Mono, Courier, monospace; font-size: 9px; }
        .muted { font-size: 9px; color: #333; font-style: italic; }
        .sig-title { font-size: 9px; font-weight: bold; text-align: center; min-height: 14px; }
        .sig-qr-wrap { text-align: center; padding: 8px 4px 4px; }
        .sig-qr-wrap img { width: 92px; height: 92px; }
        .sig-name { text-align: center; font-size: 9px; margin-top: 6px; min-height: 28px; }
        .divider-top { margin-top: 10px; }
    </style>
</head>
<body>
@php
    $checked = '[X]';
    $unchecked = '[ ]';
    $pad = "\u{00A0}\u{00A0}\u{00A0}\u{00A0}";
@endphp

<div class="sheet">
    <table class="no-border-table" style="margin-bottom: 8px;">
        <tr>
            <td style="width:62%; padding-right:6px;">
                <table class="no-border-table">
                    <tr>
                        <td style="width:78px;">
                            @if (!empty($logoDataUri))
                                <img src="{{ $logoDataUri }}" alt="" style="width:72px;height:auto;">
                            @endif
                        </td>
                        <td>
                            <div class="header-company-name">PT BPR BKK JATENG (Perseroda)</div>
                            <div class="header-address">Jl. Tanjung No. 11-A Semarang</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width:38%;">
                <table class="bound">
                    <tr>
                        <td class="hdr-box-title">FORM PERMINTAAN PERUBAHAN</td>
                    </tr>
                    <tr>
                        <td class="hdr-box-lines">
                            <div>Nomor : <strong>{{ $cr->nomor }}</strong></div>
                            <div style="margin-top:3px;">Tanggal :
                                <strong>{{ $cr->tanggal?->format('d/m/Y') ?? '' }}</strong>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="bound">
        <colgroup>
            <col class="label">
            <col class="value">
        </colgroup>
        <tr><td colspan="2" class="section-gray">DATA PEMOHON</td></tr>
        <tr>
            <td class="label-col">Divisi / Satuan Kerja</td>
            <td>{{ $cr->division?->name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label-col">Bidang</td>
            <td>{{ $cr->bidang ?: '—' }}</td>
        </tr>
        <tr><td colspan="2" class="section-gray">INFORMASI PERMINTAAN PERUBAHAN</td></tr>
        <tr>
            <td class="label-col">Nama CR</td>
            <td>{{ $cr->nama ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label-col">Sistem / aplikasi</td>
            <td>{{ $cr->application?->name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label-col">Jenis Perubahan</td>
            <td>
                <span class="cb">{{ $cr->jenis_perubahan === 'temporary' ? $checked : $unchecked }}</span>
                Temporary
                {{ $pad }}
                <span class="cb">{{ $cr->jenis_perubahan === 'permanent' ? $checked : $unchecked }}</span>
                Permanent
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div style="font-weight:bold;margin-bottom:4px;">Alasan penambahan/perubahan perlu dilakukan (centang sesuai data master)</div>
                @foreach ($reasonsForPdf as $reason)
                    <div style="margin-bottom:3px;">
                        <span class="cb">{{ $reason->id === $cr->extern_cr_change_reason_id ? $checked : $unchecked }}</span>
                        {{ $reason->name }}
                    </div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label-col">Kondisi Saat Ini</td>
            <td>{!! nl2br(e((string) ($cr->kondisi_saat_ini ?? ''))) ?: '—' !!}</td>
        </tr>
        <tr>
            <td class="label-col">Perubahan yang Diharapkan</td>
            <td>{!! nl2br(e((string) ($cr->perubahan_diharapkan ?? ''))) ?: '—' !!}</td>
        </tr>
        <tr>
            <td class="label-col">Risiko terkait bila Perubahan tidak Dijalankan</td>
            <td>{!! nl2br(e((string) ($cr->risiko_bila_tidak ?? ''))) ?: '—' !!}</td>
        </tr>
        <tr>
            <td class="label-col">Prioritas Perubahan</td>
            <td>
                <span class="cb">{{ $cr->prioritas === 'rendah' ? $checked : $unchecked }}</span> Rendah
                {{ $pad }}
                <span class="cb">{{ $cr->prioritas === 'sedang' ? $checked : $unchecked }}</span> Sedang
                {{ $pad }}
                <span class="cb">{{ $cr->prioritas === 'tinggi' ? $checked : $unchecked }}</span> Tinggi
            </td>
        </tr>
        <tr>
            <td class="label-col">Divisi yang Terlibat</td>
            <td>{{ $divisiTerlibatDisplay }}</td>
        </tr>
        <tr>
            <td class="label-col">Deskripsi Permintaan</td>
            <td>{!! nl2br(e((string) ($cr->deskripsi_permintaan ?? ''))) ?: '—' !!}</td>
        </tr>
    </table>

    <table class="bound divider-top" style="table-layout: fixed; width: 100%;">
        <tr>
            <td style="width:34%;" class="sig-title">Dibuat Oleh<br><span class="muted"></span></td>
            <td style="width:33%;" class="sig-title">Disetujui Oleh</td>
            <td style="width:33%;" class="sig-title">Ditindaklanjuti<br><span class="muted"></span></td>
        </tr>
        <tr>
            <td class="sig-qr-wrap"><img src="{{ $qrCreatorDataUri }}" alt="QR"></td>
            <td class="sig-qr-wrap"><img src="{{ $qrApproverDataUri }}" alt="QR"></td>
            <td class="sig-qr-wrap" style="color:#bbb;">……………</td>
        </tr>
        <tr>
            <td class="sig-name">
                Pembuat dokumen:<br>
                <strong>{{ $cr->creator?->name ?? 'Tidak tercatat' }}</strong><br>
                <span style="font-size:8px;">Pindai QR untuk validasi pembuat.</span>
            </td>
            <td class="sig-name">
                Menyetujui:<br>
                <strong class="muted">belum disetujui</strong><br>
                <span style="font-size:8px;">Nama penyetujui akan muncul setelah approval sistem.</span>
            </td>
            <td class="sig-name muted">Developer / vendor</td>
        </tr>
    </table>
</div>
</body>
</html>
