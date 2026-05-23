<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Kantor;
use App\Models\User;
use App\Support\IndonesianWhatsappPhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const MANAGED_ROLES = ['admin', 'manager', 'officer', 'vendor', 'cabang'];

    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.manajemen-user', [
            'users' => User::query()
                ->whereIn('role', self::MANAGED_ROLES)
                ->with([
                    'kantor.kasKantor' => fn ($q) => $q->orderBy('kode_kas'),
                    'kantor:id,kode_kantor,nama_kantor',
                ])
                ->orderBy('name')
                ->paginate(10),
            'roles' => self::MANAGED_ROLES,
            'divisions' => Division::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'kantors' => Kantor::query()
                ->where('is_active', true)
                ->with(['kasKantor' => fn ($q) => $q->orderBy('kode_kas')])
                ->orderBy('kode_kantor')
                ->get(['id', 'kode_kantor', 'nama_kantor']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'phone' => [
                Rule::requiredIf(fn () => $request->boolean('two_factor_enabled') || $request->boolean('can_authorize_extern_cr')),
                'nullable',
                'string',
                'max:32',
            ],
            'division' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), ['manager', 'officer'], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'kantor_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'cabang'),
                'nullable',
                'integer',
                Rule::exists('kantors', 'id')->where('is_active', true),
            ],
            'password' => ['required', 'string', 'min:8'],
            'can_authorize_extern_cr' => ['sometimes', 'boolean'],
        ]);

        $twoFa = $this->resolvedTwoFactorEnabled($request);
        $canAuthorizeExternCr = $request->boolean('can_authorize_extern_cr');

        $phoneWa = $this->normalizeWaPhonePayload(
            $twoFa,
            isset($validated['phone']) ? (string) $validated['phone'] : null,
        );

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phoneWa,
            'two_factor_enabled' => $twoFa,
            'can_authorize_extern_cr' => $canAuthorizeExternCr,
            'role' => $validated['role'],
            'division' => $this->normalizeDivision($validated),
            'kantor_id' => $this->normalizeKantor($validated),
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'phone' => [
                Rule::requiredIf(fn () => $request->boolean('two_factor_enabled') || $request->boolean('can_authorize_extern_cr')),
                'nullable',
                'string',
                'max:32',
            ],
            'division' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), ['manager', 'officer'], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'kantor_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'cabang'),
                'nullable',
                'integer',
                Rule::exists('kantors', 'id')->where('is_active', true),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'can_authorize_extern_cr' => ['sometimes', 'boolean'],
        ]);

        $twoFa = $this->resolvedTwoFactorEnabled($request);
        $canAuthorizeExternCr = $request->boolean('can_authorize_extern_cr');

        $phoneWa = $this->normalizeWaPhonePayload(
            $twoFa,
            isset($validated['phone']) ? (string) $validated['phone'] : null,
        );

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phoneWa,
            'two_factor_enabled' => $twoFa,
            'can_authorize_extern_cr' => $canAuthorizeExternCr,
            'role' => $validated['role'],
            'division' => $this->normalizeDivision($validated),
            'kantor_id' => $this->normalizeKantor($validated),
        ];

        if (! empty($validated['password'])) {
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
        if (! in_array($validated['role'], ['manager', 'officer'], true)) {
            return null;
        }

        return trim((string) ($validated['division'] ?? '')) ?: null;
    }

    private function normalizeKantor(array $validated): ?int
    {
        if ($validated['role'] !== 'cabang') {
            return null;
        }

        $id = $validated['kantor_id'] ?? null;

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    private function resolvedTwoFactorEnabled(Request $request): bool
    {
        return $request->boolean('two_factor_enabled');
    }

    private function normalizeWaPhonePayload(bool $twoFaEnabled, ?string $phoneInput): ?string
    {
        $raw = trim((string) ($phoneInput ?? ''));

        if (! $twoFaEnabled) {
            if ($raw === '') {
                return null;
            }

            $normalizedOptional = IndonesianWhatsappPhoneNormalizer::toWaDigits62($raw);
            if ($normalizedOptional === null) {
                throw ValidationException::withMessages([
                    'phone' => 'Format nomor HP tidak valid. Kosongkan jika tidak diperlukan atau gunakan format 08… / 628….',
                ]);
            }

            return $normalizedOptional;
        }

        if ($raw === '') {
            throw ValidationException::withMessages([
                'phone' => 'Nomor HP wajib jika verifikasi 2FA WhatsApp diaktifkan.',
            ]);
        }

        $normalized = IndonesianWhatsappPhoneNormalizer::toWaDigits62($raw);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => 'Format nomor HP tidak valid. Contoh: 0812xxxxxxxx atau 62812xxxxxxxx.',
            ]);
        }

        return $normalized;
    }
}
