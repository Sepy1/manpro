<?php

namespace App\Exports;

use App\Models\DcDrcDeviceType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DcDrcTemplateExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    /** @var list<string> */
    private array $deviceTypes;

    public function __construct()
    {
        $this->deviceTypes = DcDrcDeviceType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function collection(): Collection
    {
        return collect($this->deviceTypes)
            ->sortBy(static fn (string $type): int => strtolower($type) === 'vm' ? 1 : 0)
            ->values()
            ->map(function (string $type, int $index): array {
                $room = $index % 2 === 0 ? 'DC' : 'DRC';
                $slug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? 'DEVICE');
                $isVm = strtolower($type) === 'vm';

                return [
                    'server_name' => $isVm ? 'VM-APP-01' : $slug.'-01',
                    'tipe' => $type,
                    'vm_server' => $isVm ? 'VM-HOST-01' : null,
                    'ip_address' => '10.'.($room === 'DC' ? '10' : '20').'.'.$index.'.10',
                    'vlan' => $room === 'DC' ? '100' : '200',
                    'nic_model' => $isVm ? null : 'Model perangkat',
                    'os' => $isVm || in_array(strtolower($type), ['vm host', 'bare metal', 'physical'], true) ? 'Linux/Windows' : null,
                    'cpu' => $isVm ? 4 : null,
                    'ram_gb' => $isVm ? 8 : null,
                    'hardisk_gb' => $isVm ? 100 : null,
                    'objid_cpu' => null,
                    'objid_ram' => null,
                    'objid_ping' => null,
                    'objid_diskfree' => null,
                    'objid_traffic' => null,
                    'lokasi' => $room,
                    'fungsi' => 'Contoh fungsi perangkat',
                    'environment' => 'production',
                    'owner_team' => 'Infra',
                    'status' => 'active',
                    'keterangan' => 'Hapus baris contoh sebelum mengisi data aktual.',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'server_name', 'tipe', 'vm_server', 'ip_address', 'vlan', 'nic_model', 'os',
            'cpu', 'ram_gb', 'hardisk_gb', 'objid_cpu', 'objid_ram', 'objid_ping',
            'objid_diskfree', 'objid_traffic', 'lokasi', 'fungsi', 'environment',
            'owner_team', 'status', 'keterangan',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:U1');
                $sheet->getStyle('A1:U1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0369A1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A1:U'.max(2, count($this->deviceTypes) + 1))
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $typeList = implode(',', array_map(
                    static fn (string $type): string => str_replace('"', '""', $type),
                    $this->deviceTypes
                ));
                $this->applyListValidation($sheet, 'B2:B1000', $typeList);
                $this->applyListValidation($sheet, 'P2:P1000', 'DC,DRC');
            },
        ];
    }

    private function applyListValidation($sheet, string $range, string $values): void
    {
        $validation = $sheet->getCell(explode(':', $range)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Nilai tidak valid');
        $validation->setError('Pilih nilai dari daftar yang tersedia.');
        $validation->setFormula1('"'.$values.'"');

        $sheet->setDataValidation($range, clone $validation);
    }
}
