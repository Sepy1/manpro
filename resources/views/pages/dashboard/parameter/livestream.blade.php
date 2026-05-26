@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Parameter: Livestream" />

    <div
        class="space-y-4"
        x-data="{
            customPages: @js(old('custom_pages', $customPages)),
            imageSlides: @js($imageSlides),
            removedImageSlides: @js(old('remove_image_slides', [])),
            get activeCustomPages() {
                return this.customPages
                    .map((item) => String(item || '').trim())
                    .filter((item) => item.length > 0);
            },
            get activeImageSlides() {
                const removed = this.removedImageSlides.map((item) => String(item || '').trim());
                return this.imageSlides.filter((item) => !removed.includes(String(item || '').trim()));
            },
            isImageRemoved(path) {
                return this.removedImageSlides.includes(path);
            },
            addCustomPage() {
                this.customPages.push('');
            },
            removeCustomPage(index) {
                this.customPages.splice(index, 1);
            }
        }"
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

        <div class="content-card p-5">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pengaturan Livestream Monitoring</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Atur durasi perpindahan halaman otomatis dan pilih halaman yang ikut dalam mode livestream.
            </p>

            <form method="POST" action="{{ route('admin.parameter.livestream.update') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label for="swipe_interval_seconds" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Durasi swipe otomatis (detik)
                        </label>
                        <input
                            id="swipe_interval_seconds"
                            type="number"
                            name="swipe_interval_seconds"
                            min="{{ \App\Models\LivestreamSetting::MIN_INTERVAL_SECONDS }}"
                            max="{{ \App\Models\LivestreamSetting::MAX_INTERVAL_SECONDS }}"
                            value="{{ old('swipe_interval_seconds', $swipeIntervalSeconds) }}"
                            required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Rentang: {{ \App\Models\LivestreamSetting::MIN_INTERVAL_SECONDS }} - {{ \App\Models\LivestreamSetting::MAX_INTERVAL_SECONDS }} detik.
                        </p>
                    </div>
                    <div>
                        <label for="live_refresh_seconds" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Durasi live refresh data (detik)
                        </label>
                        <input
                            id="live_refresh_seconds"
                            type="number"
                            name="live_refresh_seconds"
                            min="{{ \App\Models\LivestreamSetting::MIN_LIVE_REFRESH_SECONDS }}"
                            max="{{ \App\Models\LivestreamSetting::MAX_LIVE_REFRESH_SECONDS }}"
                            value="{{ old('live_refresh_seconds', $liveRefreshSeconds) }}"
                            required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Rentang: {{ \App\Models\LivestreamSetting::MIN_LIVE_REFRESH_SECONDS }} - {{ \App\Models\LivestreamSetting::MAX_LIVE_REFRESH_SECONDS }} detik.
                        </p>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Halaman yang masuk mode livestream</p>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach ($pageOptions as $pageKey => $page)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    name="selected_pages[]"
                                    value="{{ $pageKey }}"
                                    @checked(in_array($pageKey, old('selected_pages', $selectedPages), true))
                                    class="rounded border-gray-300 dark:border-gray-600"
                                />
                                {{ $page['label'] }}
                            </label>
                        @endforeach
                        <template x-for="(path, index) in activeCustomPages" :key="`active-custom-${index}`">
                            <label class="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-sm text-teal-800 dark:border-teal-700 dark:bg-teal-900/20 dark:text-teal-200">
                                <input
                                    type="checkbox"
                                    checked
                                    disabled
                                    class="rounded border-teal-300 text-teal-600 opacity-80 dark:border-teal-500"
                                />
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-flex rounded-full bg-teal-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">Custom</span>
                                    <span x-text="path"></span>
                                </span>
                            </label>
                        </template>
                        <template x-for="(slidePath, index) in activeImageSlides" :key="`active-image-${index}`">
                            <label class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800 dark:border-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-200">
                                <input
                                    type="checkbox"
                                    checked
                                    disabled
                                    class="rounded border-indigo-300 text-indigo-600 opacity-80 dark:border-indigo-500"
                                />
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-flex rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">Gambar</span>
                                    <span x-text="slidePath.split('/').pop() || slidePath"></span>
                                </span>
                            </label>
                        </template>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Halaman custom dan gambar yang Anda tambahkan otomatis aktif untuk livestream.
                    </p>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Halaman custom (fitur add)</p>
                        <button
                            type="button"
                            @click="addCustomPage()"
                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-3 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10"
                        >
                            + Add halaman
                        </button>
                    </div>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                        Isi path internal aplikasi, contoh: <code>/admin/aset-ti/monitoring</code> atau <code>/aset-ti/data-center?mode=tv</code>.
                    </p>
                    <div class="space-y-2">
                        <template x-for="(page, index) in customPages" :key="`custom-page-${index}`">
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    :name="`custom_pages[${index}]`"
                                    x-model="customPages[index]"
                                    placeholder="/admin/..."
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                />
                                <button
                                    type="button"
                                    @click="removeCustomPage(index)"
                                    class="inline-flex h-10 items-center rounded-lg border border-red-400 px-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20"
                                >
                                    Hapus
                                </button>
                            </div>
                        </template>
                        <template x-if="customPages.length === 0">
                            <div class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Belum ada halaman custom.
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Slide gambar (upload)</p>
                    </div>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                        Upload gambar untuk ditampilkan sebagai slide berikutnya (JPG/PNG/GIF/WEBP, maks 5MB per file).
                    </p>
                    <input
                        type="file"
                        name="slide_images[]"
                        multiple
                        accept="image/png,image/jpeg,image/gif,image/webp"
                        class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-200 file:px-3 file:py-2 file:text-xs file:font-semibold dark:text-slate-200 dark:file:bg-slate-700"
                    />

                    <div class="mt-3 space-y-2" x-show="imageSlides.length > 0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Slide gambar saat ini</p>
                        <template x-for="(slidePath, index) in imageSlides" :key="`image-slide-${index}`">
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    name="remove_image_slides[]"
                                    :value="slidePath"
                                    x-model="removedImageSlides"
                                    class="rounded border-gray-300 dark:border-gray-600"
                                />
                                <img :src="'{{ asset('storage') }}/' + slidePath" alt="Slide livestream"
                                    class="h-10 w-16 rounded border border-gray-200 object-cover dark:border-gray-700">
                                <span class="min-w-0 flex-1 truncate" :class="isImageRemoved(slidePath) ? 'line-through opacity-60' : ''" x-text="slidePath.split('/').pop() || slidePath"></span>
                                <span class="text-[11px] font-medium text-red-600 dark:text-red-300" x-show="isImageRemoved(slidePath)">Akan dihapus</span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Simpan pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
