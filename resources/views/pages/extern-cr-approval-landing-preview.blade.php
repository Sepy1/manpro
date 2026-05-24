@php
    $cr = new \App\Models\ExternCr([
        'nomor' => '20260524-001',
        'nama' => 'Pembuatan menu laporan',
        'deskripsi_permintaan' => "1. Tidak ada monitoring\n2. Susah cari report\n3. Pending update data harian",
        'wa_authorization_decision' => null,
    ]);
    $cr->setRelation('creator', new \App\Models\User(['name' => 'Admin User']));
    $cr->setRelation('application', new \App\Models\ExternCrApplication(['name' => 'Sistem Manpro']));

    $token = '0123456789abcdef0123456789abcdef';
    $pdfUrl = '#preview-pdf';
    $approveUrl = route('extern-cr.authorize.approval.approve', ['interactionToken' => $token]);
    $rejectUrl = route('extern-cr.authorize.approval.reject', ['interactionToken' => $token]);
@endphp

@include('pages.extern-cr-approval-landing')
