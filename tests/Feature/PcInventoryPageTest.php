<?php

use App\Models\Kantor;
use App\Models\KasKantor;
use App\Models\PcInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows and filters endpoint inventory for admins', function () {
    $admin = User::factory()->admin()->create();
    $kantor = Kantor::query()->create(['kode_kantor' => '001', 'nama_kantor' => 'Cabang Utama', 'is_active' => true]);
    $kas = KasKantor::query()->create(['kantor_id' => $kantor->id, 'kode_kas' => 'K01', 'nama_kas' => 'Kas Kota', 'is_active' => true]);

    $inventory = PcInventory::query()->create([
        'hostname' => 'PC-FILTER-01', 'machine_guid' => 'guid-filter-01', 'serial_number' => 'SN-01',
        'mainboard_type' => 'Board', 'antivirus_status' => 'Aktif', 'antivirus_name' => 'Defender',
        'os_info' => 'Windows 11', 'cpu_name' => 'Intel', 'cpu_cores' => 4, 'cpu_threads' => 8,
        'ram_gb' => 8, 'disk_total_gb' => 500, 'disk_free_gb' => 200, 'disk_detail' => 'C:',
        'mac_address' => '00:11:22:33:44:55', 'ip_address' => '10.0.0.1', 'kode_kantor' => '001',
        'kantor_id' => $kantor->id, 'kas_kantor_id' => $kas->id, 'kode_kas' => 'K01',
        'nama_user' => 'Budi Filter', 'jabatan' => 'Staf / AO / Admin', 'status_perangkat' => 'Inventaris', 'last_seen_at' => now(),
    ]);
    PcInventory::query()->create([
        'hostname' => 'PC-OTHER', 'machine_guid' => 'guid-other', 'serial_number' => 'SN-02',
        'mainboard_type' => 'Board', 'antivirus_status' => 'Nonaktif', 'antivirus_name' => '-',
        'os_info' => 'Windows 10', 'cpu_name' => 'AMD', 'cpu_cores' => 4, 'cpu_threads' => 8,
        'ram_gb' => 8, 'disk_total_gb' => 500, 'disk_free_gb' => 100, 'disk_detail' => 'C:',
        'mac_address' => '00:11:22:33:44:66', 'ip_address' => '10.0.0.2', 'kode_kantor' => '001',
        'nama_user' => 'Siti', 'jabatan' => 'Teller', 'status_perangkat' => 'Device Pribadi (BYOD)', 'last_seen_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/aset-ti/end-point?keyword=FILTER&kantor_id='.$kantor->id.'&kas_kantor_id='.$kas->id)
        ->assertOk()
        ->assertSee('PC-FILTER-01')
        ->assertSee('IP: 10.0.0.1')
        ->assertSee('Antivirus:')
        ->assertSee('Defender')
        ->assertSee('Inventaris')
        ->assertSee('Cabang Utama')
        ->assertSee('Kas Kota')
        ->assertDontSee('PC-OTHER');

    $this->actingAs($admin)
        ->put(route('admin.aset-ti.end-point.update', $inventory), [
            'nama_user' => 'Budi Diperbarui',
            'jabatan' => 'Kepala Bidang',
            'status_perangkat' => 'Device Pribadi (BYOD)',
            'kantor_id' => $kantor->id,
            'kas_kantor_id' => $kas->id,
        ])
        ->assertRedirect(route('admin.aset-ti.end-point.index'));

    $this->assertDatabaseHas('pc_inventories', [
        'id' => $inventory->id,
        'nama_user' => 'Budi Diperbarui',
        'status_perangkat' => 'Device Pribadi (BYOD)',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.aset-ti.end-point.destroy', $inventory))
        ->assertRedirect(route('admin.aset-ti.end-point.index'));

    $this->assertDatabaseMissing('pc_inventories', ['id' => $inventory->id]);
});

it('forbids non-admin users from endpoint inventory', function () {
    $this->actingAs(User::factory()->create(['role' => 'officer']))
        ->get('/admin/aset-ti/end-point')
        ->assertForbidden();
});
