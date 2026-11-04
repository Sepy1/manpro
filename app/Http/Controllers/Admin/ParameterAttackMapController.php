<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttackMapSetting;
use App\Models\Kantor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParameterAttackMapController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $setting = AttackMapSetting::query()->first();

        return view('pages.dashboard.parameter.attack-map', [
            'setting' => $setting,
            'offices' => Kantor::query()->orderBy('nama_kantor')->get(['id', 'kode_kantor', 'nama_kantor', 'latitude', 'longitude']),
            'centralIps' => implode(PHP_EOL, $setting?->central_ip_ranges ?? []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'headquarters_kantor_id' => [
                'nullable',
                Rule::exists('kantors', 'id')->where(fn ($query) => $query->whereNotNull('latitude')->whereNotNull('longitude')),
            ],
            'central_ips' => ['nullable', 'string', 'max:10000'],
        ], [
            'headquarters_kantor_id.exists' => 'Kantor pusat harus memiliki latitude dan longitude.',
        ]);

        $ranges = $this->normalizeIpRanges((string) ($validated['central_ips'] ?? ''));

        AttackMapSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'headquarters_kantor_id' => $validated['headquarters_kantor_id'] ?? null,
                'central_ip_ranges' => $ranges,
            ],
        );

        return back()->with('status', 'Parameter IP dan kantor pusat berhasil disimpan.');
    }

    private function normalizeIpRanges(string $input): array
    {
        $values = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', trim($input)) ?: [])));
        if (count($values) > 200) {
            throw ValidationException::withMessages(['central_ips' => 'Maksimal 200 IP atau CIDR.']);
        }

        foreach ($values as $value) {
            [$address, $prefix] = array_pad(explode('/', $value, 2), 2, null);
            if (! filter_var($address, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['central_ips' => "Format IP tidak valid: {$value}"]);
            }
            if ($prefix !== null) {
                $maximum = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
                if (! ctype_digit($prefix) || (int) $prefix > $maximum) {
                    throw ValidationException::withMessages(['central_ips' => "Prefix CIDR tidak valid: {$value}"]);
                }
            }
        }

        return $values;
    }
}
