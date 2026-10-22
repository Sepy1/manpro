<?php

use App\Models\Kantor;
use App\Models\KasKantor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pcInventoryPayload(array $overrides = []): array
{
    return array_merge([
        'hostname' => 'PC-IT-01',
        'machine_guid' => 'a1b2c3d4-e5f6-7890-1234-567890abcdef',
        'serial_number' => 'SN123456',
        'mainboard_type' => 'ASUS PRIME B660M',
        'antivirus_status' => 'Aktif',
        'antivirus_name' => 'Microsoft Defender [Aktif, definisi terbaru]',
        'os_info' => 'Windows 11 (build 26100)',
        'cpu_name' => 'Intel Core i5-12400',
        'cpu_cores' => 6,
        'cpu_threads' => 12,
        'ram_gb' => 16.0,
        'disk_total_gb' => 476.9,
        'disk_free_gb' => 200.5,
        'disk_detail' => 'C: 477GB (sisa 201GB)',
        'mac_address' => '00:11:22:33:44:55',
        'ip_address' => '192.168.1.10',
        'kantor_id' => test()->kantor->id,
        'kas_kantor_id' => test()->kasKantor->id,
        'nama_user' => 'Budi',
        'jabatan' => 'Staf / AO / Admin',
        'status_perangkat' => 'Inventaris',
    ], $overrides);
}

beforeEach(function () {
    config()->set('services.inventory.api_key', 'test-secret-key');
    $this->kantor = Kantor::query()->create([
        'kode_kantor' => '001',
        'nama_kantor' => 'Kantor Cabang Utama',
        'is_active' => true,
    ]);
    $this->kasKantor = KasKantor::query()->create([
        'kantor_id' => $this->kantor->id,
        'kode_kas' => 'KAS-01',
        'nama_kas' => 'Kantor Kas Kota',
        'is_active' => true,
    ]);
});

it('returns active offices and their cash offices', function () {
    $this->withHeader('X-API-Key', 'test-secret-key')
        ->getJson('/api/inventory/offices')
        ->assertOk()
        ->assertJsonPath('data.0.kode_kantor', '001')
        ->assertJsonPath('data.0.kantor_kas.0.kode_kas', 'KAS-01');
});

it('stores inventory data from the python client', function () {
    $response = $this->withHeader('X-API-Key', 'test-secret-key')
        ->postJson('/api/inventory', pcInventoryPayload());

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.machine_guid', 'a1b2c3d4-e5f6-7890-1234-567890abcdef');

    $this->assertDatabaseHas('pc_inventories', [
        'machine_guid' => 'a1b2c3d4-e5f6-7890-1234-567890abcdef',
        'hostname' => 'PC-IT-01',
        'nama_user' => 'Budi',
        'status_perangkat' => 'Inventaris',
        'kode_kantor' => '001',
        'kode_kas' => 'KAS-01',
    ]);
});

it('updates an existing computer identified by machine guid', function () {
    $headers = ['X-API-Key' => 'test-secret-key'];

    $this->withHeaders($headers)->postJson('/api/inventory', pcInventoryPayload())->assertOk();
    $this->withHeaders($headers)->postJson('/api/inventory', pcInventoryPayload([
        'hostname' => 'PC-IT-RENAMED',
        'disk_free_gb' => 150.0,
    ]))->assertOk()->assertJsonPath('message', 'Data inventaris berhasil diperbarui.');

    $this->assertDatabaseCount('pc_inventories', 1);
    $this->assertDatabaseHas('pc_inventories', [
        'machine_guid' => 'a1b2c3d4-e5f6-7890-1234-567890abcdef',
        'hostname' => 'PC-IT-RENAMED',
    ]);
});

it('rejects an invalid api key', function () {
    $this->withHeader('X-API-Key', 'wrong-key')
        ->postJson('/api/inventory', pcInventoryPayload())
        ->assertUnauthorized()
        ->assertJsonPath('ok', false);
});

it('validates inventory payload', function () {
    $this->withHeader('X-API-Key', 'test-secret-key')
        ->postJson('/api/inventory', pcInventoryPayload([
            'kantor_id' => 999999,
            'nama_user' => '',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kantor_id', 'nama_user']);
});

it('only accepts supported device statuses', function () {
    $this->withHeader('X-API-Key', 'test-secret-key')
        ->postJson('/api/inventory', pcInventoryPayload(['status_perangkat' => 'Tidak Dikenal']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status_perangkat']);
});

it('rejects a cash office from a different parent office', function () {
    $otherOffice = Kantor::query()->create([
        'kode_kantor' => '002',
        'nama_kantor' => 'Kantor Cabang Lain',
        'is_active' => true,
    ]);

    $this->withHeader('X-API-Key', 'test-secret-key')
        ->postJson('/api/inventory', pcInventoryPayload(['kantor_id' => $otherOffice->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kas_kantor_id']);
});
