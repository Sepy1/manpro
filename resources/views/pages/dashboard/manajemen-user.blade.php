@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Manajemen User" />

    <div class="space-y-4" x-data="{ showAddForm: false }">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar User (Manager / Office / Vendor)</h3>
                <button type="button" @click="showAddForm = !showAddForm"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Add User
                </button>
            </div>

            <form x-show="showAddForm" x-cloak method="POST" action="{{ route('admin.manajemen-user.store') }}" class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 md:grid-cols-5 dark:border-gray-700">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Nama"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <select name="role" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
                <input type="password" name="password" required placeholder="Password"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Simpan
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
                            <th class="w-[8%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                            <th class="w-[22%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th class="w-[24%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</th>
                            <th class="w-[14%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</th>
                            <th class="w-[32%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr x-data="{ edit: false }" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $managedUser->id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span x-show="!edit">{{ $managedUser->name }}</span>
                                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.manajemen-user.update', $managedUser) }}" class="grid grid-cols-1 gap-2 md:grid-cols-4">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $managedUser->name }}" required class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-sm dark:border-gray-700 dark:text-white/90" />
                                        <input type="email" name="email" value="{{ $managedUser->email }}" required class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-sm dark:border-gray-700 dark:text-white/90" />
                                        <select name="role" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-sm dark:border-gray-700 dark:text-white/90">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected($managedUser->role === $role)>{{ ucfirst($role) }}</option>
                                            @endforeach
                                        </select>
                                        <div class="flex items-center gap-2">
                                            <input type="password" name="password" placeholder="Password baru" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-sm dark:border-gray-700 dark:text-white/90" />
                                            <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">Simpan</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $managedUser->email }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($managedUser->role) }}</td>
                                <td class="px-3 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="edit = !edit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
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
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada user role manager/office/vendor.</td>
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

