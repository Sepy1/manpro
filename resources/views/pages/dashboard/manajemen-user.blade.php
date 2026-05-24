@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Manajemen User" />

    @once
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('createUserForm', cfg => ({
                        role: cfg.initialRole ?? 'admin',
                        tfaAdmin: cfg.initialTfaAdmin ?? true,
                        authorizeCr: cfg.initialAuthorizeExternCr ?? false,
                    }));

                    Alpine.data('manajemenUserIndex', () => ({
                        showAddForm: false,
                        editModal: false,
                        editAction: '',
                        editUserId: null,
                        editRole: 'admin',
                        editTwoFa: true,
                        editAuthorizeCr: false,
                        editName: '',
                        editEmail: '',
                        editPhone: '',
                        editDivision: '',
                        editKantorId: '',
                        editPass: '',
                        openEdit(payload) {
                            this.editAction = payload.updateUrl || '';
                            this.editUserId = typeof payload.id !== 'undefined' ? payload.id : null;
                            this.editName = payload.name ?? '';
                            this.editEmail = payload.email ?? '';
                            this.editPhone = payload.phone ?? '';
                            this.editRole = payload.role ?? 'admin';
                            const tfa = typeof payload.two_factor_enabled === 'undefined' ? true : !! payload.two_factor_enabled;

                            this.editTwoFa = tfa;
                            this.editAuthorizeCr = !! payload.can_authorize_extern_cr;
                            this.editDivision = payload.division ?? '';
                            const kid = payload.kantor_id;

                            this.editKantorId = kid !== null && typeof kid !== 'undefined' ? String(kid) : '';
                            this.editPass = '';
                            this.editModal = true;
                            document.documentElement.style.overflow = 'hidden';
                        },
                        closeEdit() {
                            if (! this.editModal) {
                                return;
                            }

                            this.editModal = false;
                            this.editAction = '';
                            this.editUserId = null;
                            this.editPass = '';
                            document.documentElement.style.overflow = '';
                        },
                    }));
                });
            </script>
        @endpush
    @endonce

    <div
        class="space-y-4"
        x-data="manajemenUserIndex()"
        @keydown.escape.window="closeEdit()"
    >
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Overlay edit popup --}}
        <div
            x-show="editModal"
            x-cloak
            class="fixed inset-0 z-[100040] flex items-center justify-center bg-black/50 p-4 md:p-6"
            @click.self="closeEdit()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="user-edit-modal-title"
        >
            <div
                class="flex max-h-[min(92vh,720px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                @click.stop
            >
                <div class="shrink-0 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h3 id="user-edit-modal-title" class="text-base font-semibold text-gray-900 dark:text-white/95">Edit user</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Perbarui detail akun pengguna dengan role terkelola.<span class="font-mono" x-show="editUserId !== null" x-cloak>, ID <span x-text="editUserId"></span></span>
                    </p>
                </div>

                <form
                    method="POST"
                    x-bind:action="editAction"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5 [scrollbar-gutter:stable]"
                >
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-name">Nama</label>
                            <input
                                id="edit-user-name"
                                type="text"
                                name="name"
                                x-model="editName"
                                required
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-email">Email</label>
                            <input
                                id="edit-user-email"
                                type="email"
                                name="email"
                                x-model="editEmail"
                                required
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-phone">No. HP</label>
                            <input
                                id="edit-user-phone"
                                type="tel"
                                name="phone"
                                x-model="editPhone"
                                maxlength="24"
                                placeholder="WhatsApp Indonesia (08… / 628…)"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                x-bind:required="editTwoFa || editAuthorizeCr"
                            />
                        </div>
                        <input type="hidden" name="two_factor_enabled" :value="editTwoFa ? 1 : 0" />
                        <input type="hidden" name="can_authorize_extern_cr" :value="editAuthorizeCr ? 1 : 0" />
                        <div class="sm:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-3 dark:border-gray-700 dark:bg-slate-800/60" x-transition>
                            <label class="flex cursor-pointer items-start gap-3 text-sm text-gray-800 dark:text-gray-100">
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900"
                                    x-model="editTwoFa"
                                />
                                <span>
                                    <span class="font-semibold text-gray-900 dark:text-white">Verifikasi 2FA via WhatsApp</span>
                                    <span class="mt-1 block text-xs leading-relaxed text-gray-600 dark:text-gray-400">Untuk pengguna ini: jika diaktifkan, setiap login meminta OTP ke nomor di atas.</span>
                                </span>
                            </label>
                        </div>
                        <div class="sm:col-span-2 rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 dark:border-amber-900/40 dark:bg-amber-950/20" x-transition>
                            <label class="flex cursor-pointer items-start gap-3 text-sm text-gray-800 dark:text-gray-100">
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-900"
                                    x-model="editAuthorizeCr"
                                />
                                <span>
                                    <span class="font-semibold text-gray-900 dark:text-white">Otorisasi CR eksternal (WhatsApp)</span>
                                    <span class="mt-1 block text-xs leading-relaxed text-gray-600 dark:text-gray-400">Jika dicentang, pengguna ini termasuk penerima notifikasi template Mahadata saat ada CR eksternal baru (perlu nomor WA valid). Tombol “Setujui” / “Tolak” membuka halaman otorisasi web.</span>
                                </span>
                            </label>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-role">Role</label>
                            <select id="edit-user-role" name="role" x-model="editRole" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                @foreach ($roles as $roleOpt)
                                    <option value="{{ $roleOpt }}">{{ ucfirst($roleOpt) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-division">Divisi</label>
                            <select
                                id="edit-user-division"
                                name="division"
                                x-model="editDivision"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                x-bind:disabled="! ['manager', 'officer'].includes(editRole)"
                            >
                                <option value="">—</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division }}">{{ $division }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-kantor">Kantor (cabang)</label>
                            <select
                                id="edit-user-kantor"
                                name="kantor_id"
                                x-model="editKantorId"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                x-bind:disabled="editRole !== 'cabang'"
                            >
                                <option value="">Pilih kantor</option>
                                @foreach ($kantors as $kantor)
                                    <option value="{{ $kantor->id }}">
                                        {{ $kantor->kode_kantor }} — {{ $kantor->nama_kantor }}
                                        @if ($kantor->kasKantor->isNotEmpty())
                                            [KK: {{ $kantor->kasKantor->pluck('kode_kas')->join(', ') }}]
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" for="edit-user-pass">Password baru</label>
                            <input
                                id="edit-user-pass"
                                type="password"
                                name="password"
                                x-model="editPass"
                                placeholder="Kosongkan jika tidak diubah"
                                autocomplete="new-password"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                            />
                        </div>
                    </div>

                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                        <span x-show="editTwoFa || editAuthorizeCr">Nomor HP wajib valid bila 2FA aktif <span x-show="editTwoFa && editAuthorizeCr">&nbsp;atau&nbsp;</span><span x-show="editAuthorizeCr" x-cloak>bila menjadi penerima otorisasi CR via WhatsApp</span>.</span>
                        <span x-show="! editTwoFa && ! editAuthorizeCr" x-cloak>2FA nonaktif dan bukan otorisator CR: login tanpa OTP untuk pengguna ini — nomor HP opsional.</span>
                    </p>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button type="button" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" @click="closeEdit()">
                            Batal
                        </button>
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                            Simpan perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="content-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar User (Admin / Manager / Officer / Vendor / Cabang)</h3>
                <button type="button" @click="showAddForm = !showAddForm"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Add User
                </button>
            </div>

            @php
                $__oldTfa = old('two_factor_enabled');
                $createInitialTfa = $__oldTfa === null ? true : filter_var($__oldTfa, FILTER_VALIDATE_BOOLEAN);
                $__oldAuthorize = old('can_authorize_extern_cr');
                $createInitialAuthorize = $__oldAuthorize === null ? false : filter_var($__oldAuthorize, FILTER_VALIDATE_BOOLEAN);
            @endphp
            <form x-show="showAddForm" x-cloak x-data="createUserForm(@js(['initialRole' => old('role', 'admin'), 'initialTfaAdmin' => $createInitialTfa, 'initialAuthorizeExternCr' => $createInitialAuthorize]))" method="POST" action="{{ route('admin.manajemen-user.store') }}" class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 md:grid-cols-7 dark:border-gray-700">
                @csrf
                <input type="hidden" name="two_factor_enabled" :value="tfaAdmin ? 1 : 0" />
                <input type="hidden" name="can_authorize_extern_cr" :value="authorizeCr ? 1 : 0" />
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Nama"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="No. HP / WhatsApp"
                    x-bind:required="tfaAdmin || authorizeCr"
                    maxlength="24"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <select name="role" x-model="role" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                    @foreach ($roles as $roleOpt)
                        <option value="{{ $roleOpt }}" @selected(old('role') === $roleOpt)>{{ ucfirst($roleOpt) }}</option>
                    @endforeach
                </select>
                <select name="division" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                    :disabled="!['manager', 'officer'].includes(role)">
                    <option value="">Pilih divisi</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division }}" @selected(old('division') === $division)>{{ $division }}</option>
                    @endforeach
                </select>
                <select name="kantor_id" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                    :disabled="role !== 'cabang'">
                    <option value="">Pilih kantor</option>
                    @foreach ($kantors as $kantor)
                        <option value="{{ $kantor->id }}" @selected((string) old('kantor_id') === (string) $kantor->id)>
                            {{ $kantor->kode_kantor }} — {{ $kantor->nama_kantor }}
                            @if ($kantor->kasKantor->isNotEmpty())
                                [KK: {{ $kantor->kasKantor->pluck('kode_kas')->join(', ') }}]
                            @endif
                        </option>
                    @endforeach
                </select>
                <input type="password" name="password" required placeholder="Password"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <div class="rounded-lg border border-gray-200 bg-slate-50/90 px-3 py-3 dark:border-gray-600 dark:bg-slate-800/50 md:col-span-7" x-transition>
                    <label class="flex cursor-pointer items-start gap-3 text-sm text-gray-800 dark:text-gray-100">
                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900" x-model="tfaAdmin" />
                        <span>
                            <span class="font-semibold text-gray-900 dark:text-white">Aktifkan 2FA WhatsApp untuk pengguna ini</span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-gray-600 dark:text-gray-400">Jika dicentang, nomor HP wajib ada — login akan meminta OTP untuk setiap sesi baru pada akun dengan panel ini.</span>
                        </span>
                    </label>
                </div>
                <div class="rounded-lg border border-amber-200/80 bg-amber-50/50 px-3 py-3 dark:border-amber-900/40 dark:bg-amber-950/20 md:col-span-7" x-transition>
                    <label class="flex cursor-pointer items-start gap-3 text-sm text-gray-800 dark:text-gray-100">
                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-900" x-model="authorizeCr" />
                        <span>
                            <span class="font-semibold text-gray-900 dark:text-white">Penerima notifikasi otorisasi CR eksternal</span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-gray-600 dark:text-gray-400">Centang untuk mengirim template WhatsApp (Mahadata) ke nomor ini saat CR eksternal baru dibuat. Tombol “Setujui” / “Tolak” membuka halaman otorisasi web (tanpa webhook inbound).</span>
                        </span>
                    </label>
                </div>
                <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400 md:col-span-7">
                    <span x-show="tfaAdmin || authorizeCr">Isi nomor format Indonesia (<span class="font-mono">08…</span> atau <span class="font-mono">628…</span>) bila 2FA atau otorisasi CR aktif.</span>
                    <span x-show="! tfaAdmin && ! authorizeCr" x-cloak>Nomor HP opsional bila keduanya dimatikan.</span>
                </p>
                <div class="flex flex-wrap items-center gap-2 md:col-span-7">
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        Simpan user
                    </button>
                    <button type="button" @click="showAddForm = false" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">
                        Batal
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[6%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                            <th class="w-[18%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th class="w-[16%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</th>
                            <th class="w-[13%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No. HP</th>
                            <th class="w-[13%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Divisi</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kantor</th>
                            <th class="w-[7%] px-2 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Otor. CR</th>
                            <th class="w-[6%] px-2 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">2FA</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $managedUser->id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $managedUser->name }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $managedUser->email }}</td>
                                <td class="max-w-[9rem] break-all px-3 py-3 font-mono text-xs leading-snug text-gray-700 dark:text-gray-300" title="{{ $managedUser->phone }}">{{ $managedUser->phone ?: '—' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($managedUser->role) }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $managedUser->division ?: '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    @if ($managedUser->kantor)
                                        <span class="block font-medium text-gray-900 dark:text-white/90">{{ $managedUser->kantor->kode_kantor }}</span>
                                        <span class="text-gray-600 dark:text-gray-300">{{ $managedUser->kantor->nama_kantor }}</span>
                                        @if ($managedUser->kantor && $managedUser->kantor->kasKantor->isNotEmpty())
                                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                                KK: {{ $managedUser->kantor->kasKantor->pluck('kode_kas')->join(', ') }}
                                            </span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-2 py-3 text-[11px] text-gray-700 dark:text-gray-300">
                                    @if ($managedUser->can_authorize_extern_cr)
                                        <span class="font-semibold text-amber-800 dark:text-amber-200" title="Menerima notifikasi WhatsApp untuk otorisasi CR eksternal">Ya</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-2 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    @if ($managedUser->two_factor_enabled)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800 dark:bg-emerald-900/45 dark:text-emerald-100" title="2FA WhatsApp aktif">
                                            ✓ Aktif
                                        </span>
                                        @if (! filled($managedUser->phone))
                                            <span class="mt-1 block text-[11px] font-medium text-amber-700 dark:text-amber-400">Tanpa WA</span>
                                        @endif
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400" title="Login tanpa OTP">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10"
                                            @click="openEdit(@js([
                                                'id' => $managedUser->id,
                                                'updateUrl' => route('admin.manajemen-user.update', $managedUser),
                                                'name' => $managedUser->name,
                                                'email' => $managedUser->email,
                                                'phone' => $managedUser->phone,
                                                'role' => $managedUser->role,
                                                'division' => $managedUser->division,
                                                'kantor_id' => $managedUser->kantor_id,
                                                'two_factor_enabled' => $managedUser->two_factor_enabled,
                                                'can_authorize_extern_cr' => $managedUser->can_authorize_extern_cr,
                                            ]))"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.manajemen-user.delete', $managedUser) }}" onsubmit="return confirm('Hapus user ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-red-400 px-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada user role admin/manager/officer/vendor/cabang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
