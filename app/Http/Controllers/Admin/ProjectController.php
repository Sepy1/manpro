<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use App\Models\Vendor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const CATEGORY_BADGE_COLORS = [
        'Development Aplikasi' => 'blue',
        'Change Request' => 'purple',
        'Audit' => 'amber',
        'Infrastruktur' => 'emerald',
        'Pengadaan' => 'rose',
        'Kerjasama Vendor' => 'cyan',
    ];

    public function dashboard(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $statusCounts = Project::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $projectsByStatus = [
            'planned' => (int) ($statusCounts['planned'] ?? 0),
            'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
            'completed' => (int) ($statusCounts['completed'] ?? 0),
            'delayed' => (int) ($statusCounts['delayed'] ?? 0),
        ];

        $todoProjects = Project::query()
            ->with(['steps' => function ($query) {
                $query->where('status', '!=', 'completed')->orderBy('sort_order');
            }])
            ->withCount([
                'steps as total_steps_count',
                'steps as completed_steps_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->whereHas('steps', function ($query) {
                $query->where('status', '!=', 'completed');
            })
            ->latest()
            ->paginate(5, ['*'], 'todo_page');

        $upcomingDeadlines = ProjectStep::query()
            ->with('project:id,name,vendor_id,category,description,url,pic,deadline,period_start,period_end,status')
            ->whereIn('status', ['planned', 'in_progress', 'delayed'])
            ->whereNotNull('deadline')
            ->whereDate('deadline', '>=', Carbon::today())
            ->orderBy('deadline')
            ->paginate(5, ['*'], 'deadline_page');

        $projectsForModal = Project::query()
            ->withCount([
                'steps as total_steps_count',
                'steps as completed_steps_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->latest()
            ->get(['id', 'name', 'vendor_id', 'category', 'status', 'period_start', 'period_end', 'deadline', 'pic']);

        $overdueSteps = ProjectStep::query()
            ->whereIn('status', ['planned', 'in_progress', 'delayed'])
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', Carbon::today())
            ->count();

        return view('pages.dashboard.index', compact(
            'projectsByStatus',
            'todoProjects',
            'upcomingDeadlines',
            'overdueSteps',
            'projectsForModal'
        ) + [
            'projectCategories' => Project::CATEGORIES,
            'categoryBadgeColors' => self::CATEGORY_BADGE_COLORS,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'picUsers' => User::query()->orderBy('name')->get(['name']),
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $projectName = trim((string) $request->string('project_name'));
        $stepName = trim((string) $request->string('step_name'));
        $pic = trim((string) $request->string('pic'));
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        $projects = $this->buildFilteredProjectsQuery($request)
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return view('pages.dashboard.daftar-project', [
            'projects' => $projects,
            'projectCategories' => Project::CATEGORIES,
            'categoryBadgeColors' => self::CATEGORY_BADGE_COLORS,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'picUsers' => User::query()->orderBy('name')->get(['name']),
            'filters' => [
                'project_name' => $projectName,
                'step_name' => $stepName,
                'pic' => $pic,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ]);
    }

    public function report(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $projects = $this->buildFilteredProjectsQuery($request)
            ->with(['steps' => fn ($query) => $query->orderBy('sort_order')])
            ->latest()
            ->get();

        $todoSteps = $projects
            ->flatMap(function (Project $project) {
                return $project->steps
                    ->where('status', '!=', 'completed')
                    ->filter(fn (ProjectStep $step) => !is_null($step->deadline))
                    ->map(function (ProjectStep $step) use ($project) {
                        return [
                            'project_name' => $project->name,
                            'step_name' => $step->step_name,
                            'pic' => $step->pic,
                            'status' => $step->status,
                            'deadline' => $step->deadline,
                        ];
                    });
            })
            ->sortBy('deadline')
            ->values();

        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');
        $useAiSummary = $request->boolean('use_ai_summary');
        $summaryProjectText = $this->buildDefaultProjectSummary($projects);
        $summarySource = 'Sistem';

        if ($useAiSummary) {
            $aiSummary = $this->generateAiProjectSummary($projects, [
                'project_name' => trim((string) $request->string('project_name')),
                'step_name' => trim((string) $request->string('step_name')),
                'pic' => trim((string) $request->string('pic')),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            if ($aiSummary) {
                $summaryProjectText = $aiSummary;
                $summarySource = 'OpenAI';
            }
        }

        $pdf = Pdf::loadView('pages.dashboard.reports.project-report-pdf', [
            'projects' => $projects,
            'todoSteps' => $todoSteps,
            'generatedAt' => now(),
            'summaryProjectText' => $summaryProjectText,
            'summarySource' => $summarySource,
            'filters' => [
                'project_name' => trim((string) $request->string('project_name')),
                'step_name' => trim((string) $request->string('step_name')),
                'pic' => trim((string) $request->string('pic')),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ])->setPaper('a4', 'portrait');

        $filename = 'laporan-project-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    private function buildDefaultProjectSummary(Collection $projects): string
    {
        $totalProjects = $projects->count();
        $totalSteps = $projects->sum(fn (Project $project) => $project->steps->count());
        $completedProjects = $projects->where('status', 'completed')->count();
        $delayedProjects = $projects->where('status', 'delayed')->count();
        $inProgressProjects = $projects->where('status', 'in_progress')->count();
        $plannedProjects = $projects->where('status', 'planned')->count();

        $overdueSteps = $projects
            ->flatMap(fn (Project $project) => $project->steps)
            ->filter(fn (ProjectStep $step) => $step->status !== 'completed' && !is_null($step->deadline) && $step->deadline->isPast())
            ->count();

        $delayHighlights = $projects
            ->filter(function (Project $project) {
                return $project->status === 'delayed'
                    || $project->steps->where('status', 'delayed')->isNotEmpty();
            })
            ->map(function (Project $project) {
                $delayedStepNotes = $project->steps
                    ->where('status', 'delayed')
                    ->map(function (ProjectStep $step) {
                        $notes = collect([$step->follow_up])
                            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                            ->map(fn ($value) => trim($value))
                            ->implode(' | ');

                        return trim($step->step_name . ($notes !== '' ? ": {$notes}" : ''));
                    })
                    ->filter()
                    ->take(2)
                    ->implode(' || ');
                $reasonText = $delayedStepNotes !== '' ? $delayedStepNotes : 'belum ada catatan tindak lanjut detail.';
                $suggestionText = $this->inferSuggestionFromFollowUp($delayedStepNotes);

                return "Project: {$project->name}\nAnalisa penyebab delay: {$reasonText}\nSaran: {$suggestionText}";
            })
            ->implode("\n\n");

        $overview = "Dalam periode laporan ini terdapat {$totalProjects} project dengan {$totalSteps} step. "
            . "Komposisi status menunjukkan Planned {$plannedProjects}, In Progress {$inProgressProjects}, Completed {$completedProjects}, dan Delayed {$delayedProjects}. "
            . "Terdapat {$overdueSteps} step yang melewati deadline dan belum completed.";

        if ($delayHighlights === '') {
            return $overview . "\n\nBelum ditemukan catatan detail penyebab keterlambatan dari tindak lanjut pada data yang terfilter.";
        }

        return $overview . "\n\nIndikasi penyebab keterlambatan berdasarkan tindak lanjut step:\n\n{$delayHighlights}";
    }

    private function inferSuggestionFromFollowUp(string $followUpText): string
    {
        $normalized = mb_strtolower($followUpText);

        if ($normalized === '') {
            return 'Lengkapi catatan tindak lanjut dan tetapkan owner serta target tanggal penyelesaian blocker.';
        }

        if (str_contains($normalized, 'acc') || str_contains($normalized, 'approval') || str_contains($normalized, 'persetujuan')) {
            return 'Lakukan komunikasi ulang dan eskalasi terjadwal ke pihak pemberi persetujuan (ACC) dengan batas waktu keputusan yang jelas.';
        }

        if (str_contains($normalized, 'vendor') || str_contains($normalized, 'pihak ketiga')) {
            return 'Jadwalkan alignment mingguan dengan vendor, minta rencana pemulihan tertulis, dan monitor progres terhadap milestone baru.';
        }

        if (str_contains($normalized, 'uat') || str_contains($normalized, 'testing') || str_contains($normalized, 'uji')) {
            return 'Prioritaskan penyelesaian temuan uji, tetapkan PIC per isu, dan jalankan retest terjadwal sampai kriteria lulus terpenuhi.';
        }

        if (str_contains($normalized, 'resource') || str_contains($normalized, 'tim') || str_contains($normalized, 'manpower')) {
            return 'Lakukan rebalancing resource lintas tim untuk aktivitas kritikal dan kunci komitmen kapasitas mingguan.';
        }

        return 'Tetapkan recovery plan mingguan berbasis tindak lanjut terbaru, pastikan setiap blocker memiliki owner, due date, dan status eskalasi.';
    }

    private function buildAiProjectPayload(Collection $projects, array $filters): array
    {
        return [
            'filters' => $filters,
            'projects' => $projects->map(function (Project $project) {
                return [
                    'name' => $project->name,
                    'category' => $project->category,
                    'status' => $project->status,
                    'period_start' => optional($project->period_start)?->format('Y-m-d'),
                    'period_end' => optional($project->period_end)?->format('Y-m-d'),
                    'deadline' => optional($project->deadline)?->format('Y-m-d'),
                    'pic' => $project->pic,
                    'steps' => $project->steps->map(function (ProjectStep $step) {
                        return [
                            'step_name' => $step->step_name,
                            'status' => $step->status,
                            'follow_up' => $step->follow_up,
                            'start_date' => optional($step->start_date)?->format('Y-m-d'),
                            'end_date' => optional($step->end_date)?->format('Y-m-d'),
                            'deadline' => optional($step->deadline)?->format('Y-m-d'),
                            'pic' => $step->pic,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    private function generateAiProjectSummary(Collection $projects, array $filters): ?string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            return null;
        }

        $delayedProjects = $projects->where('status', 'delayed')->values();

        if ($delayedProjects->isEmpty()) {
            return "Tidak ada project delayed pada data filter saat ini.";
        }

        $aiPayload = $this->buildAiProjectPayload($delayedProjects, $filters);
        $delayedProjectNames = $delayedProjects
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->values()
            ->all();
        $delayedProjectCount = count($delayedProjectNames);
        $projectListText = implode(', ', $delayedProjectNames);

        $prompt = "Buat executive summary Bahasa Indonesia hanya dari data JSON berikut (tanpa asumsi, tanpa informasi eksternal). "
            . "Fokus HANYA pada project delayed. Untuk setiap project delayed, keluarkan urutan berikut:\n"
            . "Project: <nama project>\n"
            . "Analisa penyebab delay: <ringkas berbasis follow_up step delayed>\n"
            . "Saran: <aksi langsung menindaklanjuti penyebab di atas>\n"
            . "WAJIB tampilkan seluruh project delayed yang ada pada data (jumlah: {$delayedProjectCount}). "
            . "Daftar project delayed yang harus dibahas seluruhnya: {$projectListText}.\n"
            . "Pastikan Saran selalu muncul tepat setelah Analisa penyebab delay untuk project yang sama. "
            . "Jika follow_up kosong, tulis bahwa data tidak cukup dan berikan saran pengumpulan data tindak lanjut.\n\n"
            . json_encode($aiPayload, JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'input' => $prompt,
                    'max_output_tokens' => 1800,
                ]);

            if (!$response->successful()) {
                Log::warning('OpenAI summary request failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $result = $response->json();
            $text = data_get($result, 'output_text');

            if (is_array($text)) {
                $text = implode("\n", $text);
            }

            if (!is_string($text) || trim($text) === '') {
                $text = data_get($result, 'output.0.content.0.text');
            }

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (\Throwable $exception) {
            Log::warning('OpenAI summary request exception', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function buildFilteredProjectsQuery(Request $request): Builder
    {
        $projectName = trim((string) $request->string('project_name'));
        $stepName = trim((string) $request->string('step_name'));
        $pic = trim((string) $request->string('pic'));
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        return Project::query()
            ->with([
                'user',
                'vendor',
                'steps' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->when($projectName !== '', function ($query) use ($projectName) {
                $query->where('name', 'like', "%{$projectName}%");
            })
            ->when($stepName !== '', function ($query) use ($stepName) {
                $query->whereHas('steps', function ($stepQuery) use ($stepName) {
                    $stepQuery->where('step_name', 'like', "%{$stepName}%");
                });
            })
            ->when($pic !== '', function ($query) use ($pic) {
                $query->where(function ($nested) use ($pic) {
                    $nested->where('pic', 'like', "%{$pic}%")
                        ->orWhereHas('steps', function ($stepQuery) use ($pic) {
                            $stepQuery->where('pic', 'like', "%{$pic}%");
                        });
                });
            })
            ->when($periodStart, function ($query) use ($periodStart) {
                $query->whereDate('period_start', '>=', $periodStart);
            })
            ->when($periodEnd, function ($query) use ($periodEnd) {
                $query->whereDate('period_start', '<=', $periodEnd);
            });
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.insert-project', [
            'projectCategories' => Project::CATEGORIES,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'picUsers' => User::query()->orderBy('name')->get(['name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:' . implode(',', Project::CATEGORIES)],
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'pic' => ['nullable', 'string', 'max:255', 'exists:users,name'],
            'deadline' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_name' => ['required', 'string', 'max:255'],
            'steps.*.start_date' => ['nullable', 'date'],
            'steps.*.end_date' => ['nullable', 'date'],
            'steps.*.deadline' => ['nullable', 'date'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.pic' => ['nullable', 'string', 'max:255', 'exists:users,name'],
            'steps.*.follow_up' => ['nullable', 'string'],
            'steps.*.status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
        ], [
            'pic.exists' => 'PIC belum terdaftar.',
            'steps.*.pic.exists' => 'PIC belum terdaftar.',
        ]);

        if ($validated['category'] === 'Kerjasama Vendor' && empty($validated['vendor_id'])) {
            return back()
                ->withErrors(['vendor_id' => 'Vendor wajib dipilih untuk kategori Kerjasama Vendor.'])
                ->withInput();
        }

        if ($validated['category'] !== 'Kerjasama Vendor') {
            $validated['vendor_id'] = null;
        }

        DB::transaction(function () use ($validated) {
            $project = Project::create([
                'user_id' => auth()->id(),
                'vendor_id' => $validated['vendor_id'] ?? null,
                'name' => $validated['name'],
                'category' => $validated['category'],
                'description' => $validated['description'] ?? null,
                'url' => $validated['url'] ?? null,
                'pic' => $validated['pic'] ?? null,
                'deadline' => $validated['deadline'] ?? null,
                'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null,
                'status' => $validated['status'],
            ]);

            foreach (array_values($validated['steps']) as $index => $step) {
                $project->steps()->create([
                    'sort_order' => $index,
                    'step_name' => $step['step_name'],
                    'start_date' => $step['start_date'] ?? null,
                    'end_date' => $step['end_date'] ?? null,
                    'deadline' => $step['deadline'] ?? null,
                    'description' => $step['description'] ?? null,
                    'pic' => $step['pic'] ?? null,
                    'follow_up' => $step['follow_up'] ?? null,
                    'status' => $step['status'],
                ]);
            }
        });

        return redirect()
            ->route('admin.insert-project.create')
            ->with('status', 'Project dan langkah berhasil disimpan.');
    }

    public function updateProject(Request $request, Project $project): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:' . implode(',', Project::CATEGORIES)],
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'pic' => ['nullable', 'string', 'max:255', 'exists:users,name'],
            'deadline' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ], [
            'pic.exists' => 'PIC belum terdaftar.',
        ]);

        if ($validated['category'] === 'Kerjasama Vendor' && empty($validated['vendor_id'])) {
            return back()
                ->withErrors(['vendor_id' => 'Vendor wajib dipilih untuk kategori Kerjasama Vendor.'])
                ->withInput();
        }

        if ($validated['category'] !== 'Kerjasama Vendor') {
            $validated['vendor_id'] = null;
        }

        $project->update($validated);

        return redirect()
            ->route('admin.daftar-project.index')
            ->with('status', 'Data project berhasil diperbarui.');
    }

    public function deleteProject(Project $project): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $project->delete();

        return redirect()
            ->route('admin.daftar-project.index')
            ->with('status', 'Project berhasil dihapus.');
    }

    public function updateStep(Request $request, ProjectStep $step): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $incomingPic = trim((string) $request->input('pic', ''));
        $currentPic = trim((string) ($step->pic ?? ''));
        $picRules = ['nullable', 'string', 'max:255'];

        // Only enforce registered-user PIC when value is changed.
        if ($incomingPic !== $currentPic) {
            $picRules[] = 'exists:users,name';
        }

        $validated = $request->validate([
            'step_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'pic' => $picRules,
            'follow_up' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
        ], [
            'pic.exists' => 'PIC belum terdaftar.',
        ]);

        $step->update($validated);

        return redirect()
            ->back()
            ->with('status', 'Data step project berhasil diperbarui.');
    }

    public function storeStep(Request $request, Project $project): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'step_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:255', 'exists:users,name'],
            'follow_up' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
        ], [
            'pic.exists' => 'PIC belum terdaftar.',
        ]);

        $nextSortOrder = (int) ($project->steps()->max('sort_order') ?? -1) + 1;

        $project->steps()->create([
            'sort_order' => $nextSortOrder,
            ...$validated,
        ]);

        return redirect()
            ->back()
            ->with('status', 'Step project berhasil ditambahkan.');
    }

    public function deleteStep(ProjectStep $step): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $projectId = $step->project_id;
        $step->delete();

        ProjectStep::query()
            ->where('project_id', $projectId)
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->each(function (ProjectStep $remainingStep, int $index): void {
                if ((int) $remainingStep->sort_order !== $index) {
                    $remainingStep->update(['sort_order' => $index]);
                }
            });

        return redirect()
            ->back()
            ->with('status', 'Step project berhasil dihapus.');
    }
}
