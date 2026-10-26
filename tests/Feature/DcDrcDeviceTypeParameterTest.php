<?php

use App\Models\DcDrcDevice;
use App\Models\DcDrcDeviceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provides default dc drc device types and lets admins manage them', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.parameter.dc-drc-device-types.index'))
        ->assertOk()
        ->assertSee('Switch')
        ->assertSee('Router')
        ->assertSee('Firewall');

    $this->actingAs($admin)
        ->post(route('admin.parameter.dc-drc-device-types.store'), [
            'name' => 'Load Balancer',
            'sort_order' => 80,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.parameter.dc-drc-device-types.index'));

    $this->assertDatabaseHas('dc_drc_device_types', [
        'name' => 'Load Balancer',
        'is_active' => true,
    ]);
});

it('propagates a renamed type and prevents deleting a type in use', function () {
    $admin = User::factory()->admin()->create();
    $type = DcDrcDeviceType::query()->where('name', 'Switch')->firstOrFail();
    $device = DcDrcDevice::query()->create([
        'server_name' => 'SW-CORE-01',
        'device_type' => 'Switch',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.parameter.dc-drc-device-types.update', $type), [
            'name' => 'Core Switch',
            'sort_order' => 50,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.parameter.dc-drc-device-types.index'));

    expect($device->fresh()->device_type)->toBe('Core Switch');

    $this->actingAs($admin)
        ->delete(route('admin.parameter.dc-drc-device-types.destroy', $type->fresh()))
        ->assertSessionHasErrors('delete');

    $this->assertDatabaseHas('dc_drc_device_types', ['id' => $type->id]);
});

it('forbids non admins from managing dc drc device types', function () {
    $user = User::factory()->create(['role' => 'officer']);

    $this->actingAs($user)
        ->get(route('admin.parameter.dc-drc-device-types.index'))
        ->assertForbidden();
});
