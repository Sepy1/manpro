@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Daftar IP Pusat" />

    <div class="mx-auto w-full max-w-4xl">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="1" padding="p-5 lg:p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Parameter Lokasi Pusat Attack Map</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    IP yang terdaftar tidak dikirim ke ipdata. Semua IP tersebut dipetakan langsung ke koordinat kantor pusat yang dipilih.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.parameter.attack-map.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="headquarters_kantor_id" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Kantor pusat</label>
                    <select id="headquarters_kantor_id" name="headquarters_kantor_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Default — Semarang (-6.966667, 110.416664)</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}" @selected((string) old('headquarters_kantor_id', $setting?->headquarters_kantor_id) === (string) $office->id)
                                @disabled($office->latitude === null || $office->longitude === null)>
                                {{ $office->kode_kantor }} — {{ $office->nama_kantor }}
                                {{ $office->latitude !== null && $office->longitude !== null ? '('.$office->latitude.', '.$office->longitude.')' : '(koordinat belum diisi)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('headquarters_kantor_id')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Koordinat kantor terpilih menggantikan koordinat default Kota Semarang pada Attack Map.</p>
                </div>

                <div>
                    <label for="central_ips" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Daftar IP pusat</label>
                    <textarea id="central_ips" name="central_ips" rows="12" spellcheck="false"
                        placeholder="192.168.10.1&#10;10.20.0.0/16&#10;203.0.113.10"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-3 font-mono text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">{{ old('central_ips', $centralIps) }}</textarea>
                    @error('central_ips')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Satu IP atau CIDR per baris. Mendukung IPv4 dan IPv6, maksimal 200 entri. Contoh: 192.168.10.10 atau 10.20.0.0/16.
                    </p>
                </div>

                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">
                    Simpan parameter
                </button>
            </form>
        </x-dashboard.accent-card>
    </div>
@endsection
