<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProjectController extends Controller
{
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
            ->paginate(4, ['*'], 'todo_page');

        $upcomingDeadlines = ProjectStep::query()
            ->with('project:id,name')
            ->whereIn('status', ['planned', 'in_progress', 'delayed'])
            ->whereNotNull('deadline')
            ->whereDate('deadline', '>=', Carbon::today())
            ->orderBy('deadline')
            ->paginate(4, ['*'], 'deadline_page');

        $projectsForModal = Project::query()
            ->withCount([
                'steps as total_steps_count',
                'steps as completed_steps_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->latest()
            ->get(['id', 'name', 'status', 'period_start', 'period_end', 'deadline', 'pic']);

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
        ));
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $projects = Project::with([
                'user',
                'steps' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->latest()
            ->paginate(8);

        return view('pages.dashboard.daftar-project', compact('projects'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.insert-project');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'pic' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_name' => ['required', 'string', 'max:255'],
            'steps.*.start_date' => ['nullable', 'date'],
            'steps.*.end_date' => ['nullable', 'date'],
            'steps.*.deadline' => ['nullable', 'date'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.pic' => ['nullable', 'string', 'max:255'],
            'steps.*.follow_up' => ['nullable', 'string'],
            'steps.*.status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
        ]);

        DB::transaction(function () use ($validated) {
            $project = Project::create([
                'user_id' => auth()->id(),
                'name' => $validated['name'],
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
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'pic' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
        ]);

        $project->update($validated);

        return redirect()
            ->route('admin.daftar-project.index')
            ->with('status', 'Data project berhasil diperbarui.');
    }

    public function updateStep(Request $request, ProjectStep $step): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'step_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:255'],
            'follow_up' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,delayed'],
        ]);

        $step->update($validated);

        return redirect()
            ->back()
            ->with('status', 'Data step project berhasil diperbarui.');
    }
}
