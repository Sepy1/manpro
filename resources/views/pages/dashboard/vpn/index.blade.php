@extends('layouts.admin')

@section('admin-content')
<div x-data="vpnPage()" class="vpn-management-page h-full min-h-0 space-y-5 overflow-y-auto overscroll-y-contain pb-8 pr-1">
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:px-6">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-500">Infrastructure</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Manajemen VPN</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitoring server OpenVPN, sertifikat, koneksi, dan alokasi IP client.</p>
        </div>
        <button @click="openCreate()" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 transition hover:-translate-y-0.5 hover:bg-brand-600">
            <span class="text-lg leading-none">+</span> Tambah Client
        </button>
    </div>

    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-300">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $errors->first() }}</div>@endif
    @if($topologyError)<div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"><strong>CCD terkunci:</strong> {{ $topologyError }}</div>@endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Total Client',$summary['total'],'text-blue-600'], ['Online',$summary['online'],'text-green-600'], ['Offline',$summary['offline'],'text-gray-600'], ['Koneksi Hari Ini',$summary['today'],'text-violet-600'],
            ['Sertifikat Aktif',$summary['certActive'],'text-emerald-600'], ['Sertifikat Revoked',$summary['certRevoked'],'text-red-600'], ['Firewall Rules',$summary['firewall'],'text-amber-600'], ['Server',$serverStatus['active'] ? 'Aktif' : ucfirst($serverStatus['label']),$serverStatus['active'] ? 'text-green-600' : 'text-red-600']
        ] as [$label,$value,$color])
        <div @if($label === 'Firewall Rules') role="button" tabindex="0" @click="firewallModal=true" @keydown.enter.prevent="firewallModal=true" @keydown.space.prevent="firewallModal=true" title="Klik untuk melihat daftar firewall VPN" @endif class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 {{ $label === 'Firewall Rules' ? 'cursor-pointer ring-amber-400/0 hover:ring-2 hover:ring-amber-400/40' : '' }}">
            <span class="absolute inset-y-0 left-0 w-1 bg-current opacity-70 {{ $color }}"></span>
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="mt-2 truncate text-2xl font-bold {{ $color }}">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    <div x-show="firewallModal" x-cloak class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="firewallModal=false">
        <section @click.outside="firewallModal=false" class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
            <header class="flex items-start justify-between border-b border-gray-200 p-5 dark:border-gray-800">
                <div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daftar Firewall VPN</h3><p class="mt-1 text-xs text-gray-500">Policy terdaftar berdasarkan konfigurasi user, group, status, dan jadwal akses saat ini.</p></div>
                <button type="button" @click="firewallModal=false" class="rounded-lg px-3 py-1.5 text-xl text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup">&times;</button>
            </header>
            <div class="overflow-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-950"><tr><th class="px-4 py-3">Client</th><th class="px-4 py-3">IP VPN</th><th class="px-4 py-3">Sumber</th><th class="px-4 py-3">Allowed Destination</th><th class="px-4 py-3">Jadwal</th><th class="px-4 py-3">Kondisi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($firewallPolicies as $policy)
                        <tr class="align-top"><td class="px-4 py-3"><p class="font-semibold text-gray-900 dark:text-white">{{ $policy['name'] }}</p><p class="text-xs text-gray-500">CN: {{ $policy['common_name'] }}</p></td><td class="px-4 py-3 font-mono text-xs">{{ $policy['vpn_ip'] }}</td><td class="px-4 py-3 text-xs">{{ $policy['source'] }}</td><td class="px-4 py-3">@forelse($policy['destinations'] as $destination)<code class="mb-1 mr-1 inline-block rounded bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $destination }}</code>@empty<span class="text-xs font-medium text-red-600">BLOCK ALL</span>@endforelse</td><td class="px-4 py-3 text-xs">@if($policy['schedule']){{ implode(', ', array_map(fn($day) => [1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'][$day] ?? $day, $policy['schedule']['days'])) }}<br><span class="font-mono">{{ $policy['schedule']['start'] }}–{{ $policy['schedule']['end'] }}</span>@else<span class="text-gray-500">Setiap saat</span>@endif</td><td class="px-4 py-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold','bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300'=>$policy['effective_status']==='Diizinkan','bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'=>$policy['effective_status']==='Di luar jadwal','bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300'=>in_array($policy['effective_status'],['Blokir semua','Nonaktif'])])>{{ $policy['effective_status'] }}</span></td></tr>
                        @empty<tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">Belum ada policy firewall VPN terdaftar.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(320px,0.8fr)]">
        <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div><h3 class="font-semibold text-gray-900 dark:text-white">Aktivitas VPN 7 Hari</h3><p class="mt-1 text-xs text-gray-500">Perbandingan jumlah sesi dan durasi rata-rata koneksi.</p></div>
            <div id="vpnActivityChart" class="mt-2 h-64 w-full min-w-0"></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"><div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900 dark:text-white">Status Server</h3><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $serverStatus['active'] ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $serverStatus['active'] ? 'Online' : 'Bermasalah' }}</span></div>
            <dl class="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-800"><div class="flex justify-between gap-3 py-3"><dt class="text-gray-500">Service</dt><dd class="truncate font-mono text-xs font-semibold">openvpn-server@server</dd></div><div class="flex justify-between py-3"><dt class="text-gray-500">Network</dt><dd class="font-mono font-semibold">10.200.0.0/24</dd></div><div class="flex justify-between py-3"><dt class="text-gray-500">IP Server</dt><dd class="font-mono font-semibold">10.200.0.1</dd></div><div class="flex justify-between py-3"><dt class="text-gray-500">Topology</dt><dd class="font-semibold {{ $topology === 'subnet' ? 'text-green-600' : 'text-amber-600' }}">{{ $topology ?: 'Tidak terverifikasi' }}</dd></div></dl>
            <p class="mt-5 rounded-lg bg-blue-50 p-3 text-xs text-blue-800 dark:bg-blue-950/30 dark:text-blue-300">CCD hanya dibuat setelah directive <code>topology</code> pada server.conf berhasil dibaca. Topology non-subnet tidak akan ditulis otomatis.</p>
            <form method="POST" action="{{ route('admin.vpn.server.restart') }}" class="mt-4" onsubmit="return confirm('Restart service OpenVPN sekarang? Koneksi client dapat terputus sesaat.')">@csrf<button class="w-full rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-300">Restart Service OpenVPN</button></form>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800"><div><h3 class="font-semibold text-gray-900 dark:text-white">VPN Client</h3><p class="mt-1 text-xs text-gray-500">Inventaris perangkat, sertifikat, dan koneksi VPN.</p></div><span class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs text-gray-500 dark:bg-gray-800">{{ $summary['total'] }} client terdaftar</span></div>
        <div class="overflow-x-auto"><table class="min-w-[1100px] w-full text-left text-sm"><thead class="bg-gray-50/80 text-[11px] uppercase tracking-wider text-gray-500 dark:bg-gray-950/70"><tr><th class="px-4 py-3">Client</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">IP VPN</th><th class="px-4 py-3">Sertifikat</th><th class="px-4 py-3">Koneksi</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse($clients as $client)
            @php($online = in_array($client->id, $onlineIds))
            <tr><td class="p-3"><p class="font-semibold text-gray-900 dark:text-white">{{ $client->name }}</p><p class="text-xs text-gray-500">{{ $client->username }} · CN: <span class="font-mono">{{ $client->common_name }}</span></p></td><td class="p-3"><p>{{ $client->department ?: '-' }}</p><p class="text-xs text-gray-500">{{ $client->branch_office ?: '-' }}{{ $client->inventory_number ? ' · '.$client->inventory_number : '' }}</p></td><td class="p-3 font-mono">{{ $client->vpn_ip }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-xs {{ $client->certificate_status === 'active' ? 'bg-green-100 text-green-700' : ($client->certificate_status === 'revoked' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">{{ $client->certificate_status }}</span><p class="mt-1 text-xs text-gray-500">{{ $client->certificate_expires_at?->format('d M Y') ?: 'Belum dibuat' }}</p></td><td class="p-3"><span class="font-medium {{ $online ? 'text-green-600' : 'text-gray-500' }}">● {{ $online ? 'Online' : 'Offline' }}</span><p class="text-xs text-gray-500">{{ $client->connections_count }} sesi</p></td><td class="p-3">{{ ucfirst($client->status) }}</td>
            <td class="p-3"><div class="flex justify-end gap-1"><button @click='openHistory(@json($client))' class="rounded border px-2 py-1 text-xs">Riwayat</button><button @click='openEdit(@json($client))' class="rounded border px-2 py-1 text-xs">Edit</button>
                @if($client->certificate_status !== 'active')<form method="POST" action="{{ route('admin.vpn.clients.certificate',$client) }}">@csrf<button class="rounded border border-green-300 px-2 py-1 text-xs text-green-700">Generate Cert</button></form>@else<a href="{{ route('admin.vpn.clients.download',$client) }}" class="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700">.ovpn</a><form method="POST" action="{{ route('admin.vpn.clients.revoke',$client) }}" onsubmit="return confirm('Revoke sertifikat client ini?')">@csrf<button class="rounded border border-red-300 px-2 py-1 text-xs text-red-700">Revoke</button></form>@endif
                <form method="POST" action="{{ route('admin.vpn.clients.toggle',$client) }}">@csrf @method('PATCH')<button class="rounded border px-2 py-1 text-xs">{{ $client->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                <form method="POST" action="{{ route('admin.vpn.clients.destroy',$client) }}" onsubmit="return confirm('Hapus client dan revoke sertifikat aktif?')">@csrf @method('DELETE')<button class="rounded border border-red-300 px-2 py-1 text-xs text-red-700">Hapus</button></form>
            </div></td></tr>
        @empty<tr><td colspan="7" class="p-10 text-center text-gray-500">Belum ada client VPN.</td></tr>@endforelse</tbody></table></div>
        @if($clients->hasPages())<div class="border-t p-4 dark:border-gray-800">{{ $clients->links() }}</div>@endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><h3 class="font-semibold text-gray-900 dark:text-white">Aktivitas Administrator Terbaru</h3><div class="mt-4 space-y-3">@forelse($activities as $activity)<div class="flex justify-between gap-4 border-b border-gray-100 pb-3 text-sm dark:border-gray-800"><div><strong>{{ $activity->user?->name ?? 'Sistem' }}</strong> — {{ $activity->description }}<p class="text-xs text-gray-500">{{ $activity->action }} · {{ $activity->ip_address }}</p></div><time class="whitespace-nowrap text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</time></div>@empty<p class="text-sm text-gray-500">Belum ada aktivitas.</p>@endforelse</div></div>

    <div x-show="modal" x-cloak class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="modal=false"><div @click.outside="modal=false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 dark:bg-gray-900"><div class="flex justify-between"><h3 class="text-lg font-semibold" x-text="editing ? 'Edit Client VPN' : 'Tambah Client VPN'"></h3><button @click="modal=false">✕</button></div>
        <form method="POST" :action="editing ? '{{ url('/admin/vpn/clients') }}/'+form.id : '{{ route('admin.vpn.clients.store') }}'" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
            @foreach([['name','Nama pengguna','text',true],['username','Username','text',true],['common_name','Common Name (CN)','text',true],['department','Departemen/divisi','text',false],['branch_office','Kantor cabang','text',false],['inventory_number','Nomor inventaris','text',false],['vpn_ip','IP VPN (kosong = otomatis)','text',false]] as [$key,$label,$type,$required])<label class="text-sm"><span class="mb-1 block font-medium">{{ $label }}</span><input name="{{ $key }}" type="{{ $type }}" x-model="form.{{ $key }}" {{ $required ? 'required' : '' }} class="w-full rounded-lg border-gray-300 bg-transparent text-sm dark:border-gray-700"></label>@endforeach
            <label x-show="editing" class="text-sm"><span class="mb-1 block font-medium">Status</span><select name="status" x-model="form.status" class="w-full rounded-lg border-gray-300 bg-transparent dark:border-gray-700"><option value="active">Aktif</option><option value="disabled">Nonaktif</option></select></label>
            <div class="sm:col-span-2 flex justify-end gap-2"><button type="button" @click="modal=false" class="rounded-lg border px-4 py-2">Batal</button><button class="rounded-lg bg-brand-500 px-4 py-2 font-semibold text-white">Simpan & Tulis CCD</button></div>
        </form></div></div>
    <div x-show="historyModal" x-cloak class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="historyModal=false"><div @click.outside="historyModal=false" class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6 dark:bg-gray-900"><div class="flex justify-between"><div><h3 class="text-lg font-semibold">Riwayat Koneksi</h3><p class="text-sm text-gray-500" x-text="historyClient.name+' · '+historyClient.common_name"></p></div><button @click="historyModal=false">✕</button></div><div class="mt-5 overflow-x-auto"><table class="min-w-[700px] w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-950"><tr><th class="p-3">Terhubung</th><th class="p-3">Terputus</th><th class="p-3">Real IP</th><th class="p-3">VPN IP</th><th class="p-3">Transfer</th><th class="p-3">Alasan</th></tr></thead><tbody><template x-for="session in historyClient.connections || []" :key="session.id"><tr class="border-b dark:border-gray-800"><td class="p-3" x-text="formatDate(session.connected_at)"></td><td class="p-3" x-text="session.disconnected_at ? formatDate(session.disconnected_at) : 'Masih online'"></td><td class="p-3 font-mono" x-text="session.real_ip || '-'"></td><td class="p-3 font-mono" x-text="session.vpn_ip || '-'"></td><td class="p-3" x-text="formatBytes((session.bytes_received || 0)+(session.bytes_sent || 0))"></td><td class="p-3" x-text="session.disconnect_reason || '-'"></td></tr></template><tr x-show="!(historyClient.connections || []).length"><td colspan="6" class="p-8 text-center text-gray-500">Belum ada riwayat koneksi.</td></tr></tbody></table></div></div></div>
</div>
@endsection

@push('scripts')
<script>
function vpnPage(){return{modal:false,historyModal:false,firewallModal:false,editing:false,form:{},historyClient:{connections:[]},openCreate(){this.editing=false;this.form={status:'active'};this.modal=true},openEdit(client){this.editing=true;this.form={...client};this.modal=true},openHistory(client){this.historyClient=client;this.historyModal=true},formatDate(value){return new Intl.DateTimeFormat('id-ID',{dateStyle:'medium',timeStyle:'short'}).format(new Date(value))},formatBytes(value){if(value<1024)return value+' B';if(value<1048576)return(value/1024).toFixed(1)+' KB';if(value<1073741824)return(value/1048576).toFixed(1)+' MB';return(value/1073741824).toFixed(1)+' GB'}}}
document.addEventListener('DOMContentLoaded', () => {
    const element = document.querySelector('#vpnActivityChart');
    if (!window.ApexCharts || !element) return;

    new ApexCharts(element, {
        chart: {
            height: 256,
            type: 'line',
            fontFamily: 'inherit',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { speed: 450 },
        },
        series: [
            { name: 'Koneksi', type: 'column', data: @json($chart['connections']) },
            { name: 'Durasi rata-rata', type: 'line', data: @json($chart['durations']) },
        ],
        colors: ['#465fff', '#12b76a'],
        stroke: { curve: 'smooth', width: [0, 3] },
        fill: { opacity: [0.85, 1] },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '42%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148, 163, 184, 0.16)', strokeDashArray: 4 },
        xaxis: {
            categories: @json($chart['labels']),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: '#94a3b8' } },
        },
        yaxis: [
            {
                min: 0,
                forceNiceScale: true,
                decimalsInFloat: 0,
                title: { text: 'Koneksi', style: { color: '#94a3b8', fontWeight: 500 } },
                labels: { style: { colors: '#94a3b8' } },
            },
            {
                opposite: true,
                min: 0,
                forceNiceScale: true,
                title: { text: 'Menit', style: { color: '#94a3b8', fontWeight: 500 } },
                labels: { style: { colors: '#94a3b8' }, formatter: value => Math.round(value) },
            },
        ],
        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: '#94a3b8' } },
        tooltip: {
            shared: true,
            y: [
                { formatter: value => `${Math.round(value)} koneksi` },
                { formatter: value => `${Number(value).toFixed(1)} menit` },
            ],
        },
    }).render();
});
</script>
@endpush
