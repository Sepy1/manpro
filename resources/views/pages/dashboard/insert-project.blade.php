@extends('layouts.admin')

@section('admin-content')
    {{-- Mengisi tinggi area main di bawah header; kartu setinggi; scroll hanya di dalam kartu --}}
    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden md:gap-3">
        <div class="shrink-0 [&>div]:!mb-2">
            <x-common.page-breadcrumb pageTitle="Insert Project" />
        </div>

        @if (session('status'))
            <div class="shrink-0 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('admin.insert-project.store') }}"
            class="grid min-h-0 w-full min-w-0 flex-1 grid-cols-1 grid-rows-[minmax(0,1fr)_minmax(0,1fr)] gap-3 overflow-hidden md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:grid-rows-[minmax(0,1fr)] md:gap-4 md:items-stretch"
            x-data="insertProjectForm()"
            @submit="prepareSteps($event)"
        >
            @csrf

            {{-- Card kiri --}}
            <div class="flex min-h-0 h-full max-h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="shrink-0 border-b border-gray-100 px-4 py-3 dark:border-gray-800 md:px-5">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Data Project</h3>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-3 md:px-5 md:py-4">
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Project</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Keterangan</label>
                            <textarea name="description" rows="3" placeholder="Deskripsi singkat project"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">URL</label>
                            <input type="url" name="url" value="{{ old('url') }}" placeholder="https://"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                            @error('url')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">PIC</label>
                            <input type="text" name="pic" value="{{ old('pic') }}" placeholder="Nama PIC"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                            @error('pic')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Periode mulai</label>
                                <input type="date" name="period_start" value="{{ old('period_start') }}"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                                @error('period_start')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Periode selesai</label>
                                <input type="date" name="period_end" value="{{ old('period_end') }}"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                                @error('period_end')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Deadline Project</label>
                            <input type="date" name="deadline" value="{{ old('deadline') }}"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                            @error('deadline')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status Project</label>
                            <select name="status"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                                <option value="planned" @selected(old('status', 'planned') === 'planned')>Planned</option>
                                <option value="in_progress" @selected(old('status') === 'in_progress')>In Progress</option>
                                <option value="completed" @selected(old('status') === 'completed')>Completed</option>
                                <option value="delayed" @selected(old('status') === 'delayed')>Delayed</option>
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-800 md:px-5">
                    <button type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-5 py-2 text-sm font-medium text-white hover:bg-brand-600">
                        Simpan Project
                    </button>
                </div>
            </div>

            {{-- Card kanan --}}
            <div class="flex min-h-0 h-full max-h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="shrink-0 border-b border-gray-100 px-4 py-3 dark:border-gray-800 md:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Langkah Project</h3>
                        <span class="max-w-[14rem] text-right text-xs text-gray-500 dark:text-gray-400 md:text-sm">
                            Geser untuk pindah langkah
                        </span>
                    </div>
                </div>

                <div
                    class="relative min-h-0 w-full min-w-0 flex-1 touch-pan-y overflow-hidden border-b border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-white/[0.02]"
                    @touchstart.passive="touchStartX = $event.touches[0].clientX"
                    @touchend="handleSwipe($event)"
                >
                    <div
                        class="flex h-full min-h-0 w-full min-w-0 transition-transform duration-300 ease-out"
                        :style="carouselStyle()"
                    >
                        <template x-for="(step, index) in steps" :key="index">
                            <div
                                class="max-h-full min-w-0 shrink-0 overflow-x-hidden overflow-y-auto overscroll-y-contain px-4 py-3 sm:px-5 sm:py-4"
                                :style="'width: calc(100% / ' + steps.length + ')'"
                            >
                                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-brand-600 dark:text-white/90">
                                    Langkah <span x-text="index + 1"></span> dari <span x-text="steps.length"></span>
                                </p>
                                <div class="space-y-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Step</label>
                                        <input type="text" :name="'steps[' + index + '][step_name]'" x-model="step.step_name" required
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Start Date</label>
                                        <input type="date" :name="'steps[' + index + '][start_date]'" x-model="step.start_date"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">End Date</label>
                                        <input type="date" :name="'steps[' + index + '][end_date]'" x-model="step.end_date"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Deadline</label>
                                        <input type="date" :name="'steps[' + index + '][deadline]'" x-model="step.deadline"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Keterangan</label>
                                        <textarea rows="2" :name="'steps[' + index + '][description]'" x-model="step.description"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"></textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">PIC</label>
                                        <input type="text" :name="'steps[' + index + '][pic]'" x-model="step.pic"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tindak Lanjut</label>
                                        <textarea rows="2" :name="'steps[' + index + '][follow_up]'" x-model="step.follow_up"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"></textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
                                        <select :name="'steps[' + index + '][status]'" x-model="step.status"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                            <option value="planned">Planned</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                            <option value="delayed">Delayed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="shrink-0 space-y-2 px-4 py-3 md:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="prevStep()"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
                                :disabled="currentIndex === 0"
                                aria-label="Langkah sebelumnya">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="nextStep()"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
                                :disabled="currentIndex >= steps.length - 1"
                                aria-label="Langkah berikutnya">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-for="(_, i) in steps" :key="'dot-' + i">
                                <button type="button" @click="currentIndex = i"
                                    class="h-2 rounded-full transition-all"
                                    :class="i === currentIndex ? 'w-5 bg-brand-500' : 'w-2 bg-gray-300 dark:bg-gray-600'"
                                    :aria-label="'Langkah ' + (i + 1)"></button>
                            </template>
                        </div>
                        <button type="button" @click="addStep()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-brand-500 px-3 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10 md:text-sm">
                            <svg class="h-3.5 w-3.5 md:h-4 md:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah langkah
                        </button>
                    </div>

                    @if ($errors->has('steps') || $errors->has('steps.*'))
                        <p class="text-sm text-red-600 dark:text-red-400">Periksa kembali isian langkah project.</p>
                    @endif
                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'steps.'))
                            @foreach ($messages as $msg)
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $msg }}</p>
                            @endforeach
                        @endif
                    @endforeach

                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('insertProjectForm', () => ({
                currentIndex: 0,
                touchStartX: null,
                steps: [
                    {
                        step_name: '',
                        start_date: '',
                        end_date: '',
                        deadline: '',
                        description: '',
                        pic: '',
                        follow_up: '',
                        status: 'planned',
                    },
                ],
                init() {
                    const oldSteps = @json(array_values(old('steps', [])));
                    if (Array.isArray(oldSteps) && oldSteps.length > 0) {
                        this.steps = oldSteps.map((s) => ({
                            step_name: s.step_name ?? '',
                            start_date: s.start_date ?? '',
                            end_date: s.end_date ?? '',
                            deadline: s.deadline ?? '',
                            description: s.description ?? '',
                            pic: s.pic ?? '',
                            follow_up: s.follow_up ?? '',
                            status: s.status ?? 'planned',
                        }));
                        this.currentIndex = 0;
                    }
                },
                carouselStyle() {
                    const n = this.steps.length || 1;
                    return {
                        width: (n * 100) + '%',
                        transform: 'translateX(-' + ((100 / n) * this.currentIndex) + '%)',
                    };
                },
                prevStep() {
                    if (this.currentIndex > 0) this.currentIndex--;
                },
                nextStep() {
                    if (this.currentIndex < this.steps.length - 1) this.currentIndex++;
                },
                addStep() {
                    this.steps.push({
                        step_name: '',
                        start_date: '',
                        end_date: '',
                        deadline: '',
                        description: '',
                        pic: '',
                        follow_up: '',
                        status: 'planned',
                    });
                    this.currentIndex = this.steps.length - 1;
                },
                handleSwipe(event) {
                    if (this.touchStartX == null) return;
                    const endX = event.changedTouches[0].clientX;
                    const dx = endX - this.touchStartX;
                    this.touchStartX = null;
                    if (dx < -48) this.nextStep();
                    else if (dx > 48) this.prevStep();
                },
                prepareSteps(event) {
                    const invalid = this.steps.some((s) => !String(s.step_name || '').trim());
                    if (invalid) {
                        event.preventDefault();
                        alert('Setiap langkah wajib diisi nama Step.');
                    }
                },
            }));
        });
    </script>
@endpush
