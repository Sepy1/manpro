<?php

namespace App\Imports;

use App\Models\VpnClient;
use App\Models\VpnGroup;
use App\Services\OpenVpnService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VpnClientsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(private readonly OpenVpnService $openVpn, private readonly int $userId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($row->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $excelRow = $index + 2;
            try {
                $this->importRow($row->toArray());
                $this->imported++;
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$excelRow}: {$e->getMessage()}";
            }
        }
    }

    private function importRow(array $row): void
    {
        $data = Validator::make($row, [
            'nama_pengguna' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:vpn_clients,username'],
            'common_name' => ['required', 'regex:/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,31}\z/', 'unique:vpn_clients,common_name'],
            'departemen' => ['nullable', 'string', 'max:255'],
            'kantor_cabang' => ['nullable', 'string', 'max:255'],
            'nomor_inventaris' => ['nullable', 'string', 'max:100'],
            'ip_vpn' => ['nullable', 'ipv4', 'unique:vpn_clients,vpn_ip'],
            'group_vpn' => ['nullable', 'string', 'max:255'],
            'allowed_ip_destination' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ])->validate();

        $group = filled($data['group_vpn'] ?? null)
            ? VpnGroup::where('name', trim($data['group_vpn']))->firstOrFail()
            : null;
        $destinations = $this->parseDestinations((string) ($data['allowed_ip_destination'] ?? ''));

        DB::transaction(function () use ($data, $group, $destinations) {
            $ip = filled($data['ip_vpn'] ?? null) ? trim($data['ip_vpn']) : $this->nextIp();
            if (! preg_match('/\A10\.200\.0\.(?:[2-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-4])\z/', $ip)) {
                throw new \RuntimeException('IP VPN harus 10.200.0.2–10.200.0.254.');
            }

            $client = VpnClient::create([
                'name' => $data['nama_pengguna'], 'username' => $data['username'],
                'common_name' => $data['common_name'], 'department' => $data['departemen'] ?? null,
                'branch_office' => $data['kantor_cabang'] ?? null,
                'inventory_number' => $data['nomor_inventaris'] ?? null, 'vpn_ip' => $ip,
                'vpn_group_id' => $group?->id, 'allowed_destinations' => $destinations,
                'status' => ($data['status'] ?? null) ?: 'active', 'certificate_status' => 'not_generated',
                'created_by' => $this->userId,
            ]);
            $this->openVpn->writeCcd($client);
            $this->openVpn->syncAccessPolicy($client);
        });
    }

    private function nextIp(): string
    {
        $used = VpnClient::lockForUpdate()->pluck('vpn_ip')->flip();
        for ($host = 2; $host <= 254; $host++) {
            if (! isset($used["10.200.0.{$host}"])) {
                return "10.200.0.{$host}";
            }
        }
        throw new \RuntimeException('Pool IP VPN penuh.');
    }

    private function parseDestinations(string $value): array
    {
        $items = array_values(array_unique(array_filter(preg_split('/[\s,]+/', trim($value)))));
        foreach ($items as $item) {
            [$ip, $prefix] = array_pad(explode('/', $item, 2), 2, null);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix > 32))) {
                throw new \RuntimeException("Allowed IP/CIDR tidak valid: {$item}");
            }
        }
        return $items;
    }
}
