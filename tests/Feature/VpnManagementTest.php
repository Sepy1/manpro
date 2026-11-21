<?php

use App\Models\User;
use App\Services\OpenVpnService;
use Mockery\MockInterface;

it('only allows admins to open vpn management', function () {
    $this->actingAs(User::factory()->create(['role' => 'officer']))
        ->get(route('admin.vpn.index'))->assertForbidden();
});

it('shows the vpn dashboard to an admin', function () {
    $this->mock(OpenVpnService::class, function (MockInterface $mock) {
        $mock->shouldReceive('topology')->once()->andReturn('subnet');
        $mock->shouldReceive('serverStatus')->once()->andReturn(['active' => true, 'label' => 'active']);
        $mock->shouldReceive('firewallRuleCount')->once()->andReturn(4);
        $mock->shouldReceive('connectedClients')->once()->andReturn([]);
    });
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.vpn.index'))
        ->assertOk()->assertSee('Manajemen VPN')->assertSee('10.200.0.0/24');
});

it('shows vpn user management to an admin', function () {
    $this->mock(OpenVpnService::class, function (MockInterface $mock) {
        $mock->shouldReceive('connectedClients')->once()->andReturn([]);
    });

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.vpn.users.index'))
        ->assertOk()
        ->assertSee('Manajemen User VPN')
        ->assertSee('Download File Import', false);
});

it('shows vpn group management to an admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.vpn.groups.index'))
        ->assertOk()
        ->assertSee('Group &amp; Allowed IP', false)
        ->assertSee('Allowed IP user');
});
