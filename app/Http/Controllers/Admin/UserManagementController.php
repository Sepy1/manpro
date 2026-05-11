<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const MANAGED_ROLES = ['admin', 'manager', 'officer', 'vendor'];

    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.manajemen-user', [
            'users' => User::query()
                ->whereIn('role', self::MANAGED_ROLES)
                ->orderBy('name')
                ->paginate(10),
            'roles' => self::MANAGED_ROLES,
            'divisions' => Division::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'division' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), ['manager', 'officer'], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'division' => $this->normalizeDivision($validated),
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.manajemen-user.index')
            ->with('status', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless(in_array($user->role, self::MANAGED_ROLES, true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'division' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), ['manager', 'officer'], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'division' => $this->normalizeDivision($validated),
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return redirect()->route('admin.manajemen-user.index')
            ->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless(in_array($user->role, self::MANAGED_ROLES, true), 403);

        if ($user->is(auth()->user())) {
            return redirect()->route('admin.manajemen-user.index')
                ->withErrors(['user' => 'Admin tidak dapat menghapus akun sendiri.']);
        }

        if ($user->role === 'admin' && User::query()->where('role', 'admin')->count() <= 1) {
            return redirect()->route('admin.manajemen-user.index')
                ->withErrors(['user' => 'Minimal harus ada satu admin aktif.']);
        }

        $user->delete();

        return redirect()->route('admin.manajemen-user.index')
            ->with('status', 'User berhasil dihapus.');
    }

    private function normalizeDivision(array $validated): ?string
    {
        if (!in_array($validated['role'], ['manager', 'officer'], true)) {
            return null;
        }

        return trim((string) ($validated['division'] ?? '')) ?: null;
    }
}

