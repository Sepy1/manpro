<?php

use App\Exports\DcDrcDevicesExport;
use App\Exports\DcDrcTemplateExport;
use App\Imports\DcDrcDevicesImport;
use App\Models\DcDrcDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

it('builds the dc drc template from device type parameters with excel validations', function () {
    $content = ExcelFacade::raw(new DcDrcTemplateExport, Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'dc-drc-template-');
    file_put_contents($path, $content);

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();

        expect($sheet->getCell('B1')->getValue())->toBe('tipe')
            ->and($sheet->getCell('P1')->getValue())->toBe('lokasi')
            ->and($sheet->getDataValidation('B2')->getFormula1())->toContain('Switch', 'Router', 'Firewall')
            ->and($sheet->getDataValidation('P2')->getFormula1())->toBe('"DC,DRC"')
            ->and($sheet->getFreezePane())->toBe('A2');
    } finally {
        @unlink($path);
    }
});

it('validates and normalizes imported device type and room values', function () {
    $import = new DcDrcDevicesImport;
    $row = [
        'server_name' => 'SW-DRC-01',
        'tipe' => 'switch',
        'lokasi' => 'drc',
        'ip_address' => '10.20.0.10',
    ];

    expect(Validator::make($row, $import->rules())->passes())->toBeTrue();

    $device = $import->model($row);
    $device->save();

    expect($device->device_type)->toBe('Switch')
        ->and($device->site)->toBe('DRC');

    expect(Validator::make([
        'server_name' => 'INVALID-01',
        'tipe' => 'Unknown Appliance',
        'lokasi' => 'Gudang',
    ], $import->rules())->passes())->toBeFalse();
});

it('exports hosts before vms and resolves every location to its simulation room', function () {
    $vm = DcDrcDevice::query()->create([
        'server_name' => 'VM-01',
        'device_type' => 'VM',
        'site' => 'Disaster Recovery Center',
    ]);
    $host = DcDrcDevice::query()->create([
        'server_name' => 'HOST-01',
        'device_type' => 'vm host',
        'site' => 'Data Center',
    ]);

    $rows = (new DcDrcDevicesExport)->collection()->values();

    expect($rows[0]['server_name'])->toBe($host->server_name)
        ->and($rows[0]['lokasi'])->toBe('DC')
        ->and($rows[1]['server_name'])->toBe($vm->server_name)
        ->and($rows[1]['lokasi'])->toBe('DRC');
});
