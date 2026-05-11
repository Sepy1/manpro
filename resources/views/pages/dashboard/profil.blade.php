@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Profil" />

    @php
        $profileUser = auth()->user();
    @endphp

    <div class="space-y-4"
        x-data="{
            showEditProfileModal: @js($errors->any() && !$errors->getBag('updatePassword')->any()),
            showPasswordModal: @js($errors->getBag('updatePassword')->any()),
        }">
        @if (session('status') === 'profile-updated')
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                Profil berhasil diperbarui.
            </div>
        @endif
        @if (session('status') === 'password-updated')
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                Password berhasil diperbarui.
            </div>
        @endif
        @if ($errors->any() && !$errors->getBag('updatePassword')->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif
        @if ($errors->getBag('updatePassword')->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->getBag('updatePassword')->first() }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Profil User</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[8%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                            <th class="w-[22%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th class="w-[28%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</th>
                            <th class="w-[14%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</th>
                            <th class="w-[14%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Divisi</th>
                            <th class="w-[24%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $profileUser?->id }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $profileUser?->name }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $profileUser?->email }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($profileUser?->role ?? '-') }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $profileUser?->division ?: '-' }}</td>
                            <td class="px-3 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="showEditProfileModal = true"
                                        class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                        Edit Profile
                                    </button>
                                    <button type="button" @click="showPasswordModal = true"
                                        class="inline-flex h-9 items-center rounded-lg border border-amber-400 px-3 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:border-amber-500 dark:text-amber-300 dark:hover:bg-amber-900/20">
                                        Ubah Password
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="showEditProfileModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showEditProfileModal = false">
            <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Edit Profile</h4>
                    <button type="button" @click="showEditProfileModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form action="{{ route('admin.profil.update-profile') }}" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama</label>
                        <input type="text" name="name" value="{{ old('name', $profileUser?->name) }}" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                        <input type="email" name="email" value="{{ old('email', $profileUser?->email) }}" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            Simpan Perubahan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showPasswordModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showPasswordModal = false">
            <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Ubah Password</h4>
                    <button type="button" @click="showPasswordModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form action="{{ route('admin.profil.update-password') }}" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Password Saat Ini</label>
                        <input type="password" name="current_password" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Password Baru</label>
                        <input type="password" name="password" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">
                            Simpan Password Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
