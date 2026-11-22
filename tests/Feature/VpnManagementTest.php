<?php

use App\Models\User;
use App\Models\VpnClient;
use App\Models\VpnGroup;
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

it('stores an access schedule on a vpn group', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.vpn.groups.store'), [
            'name' => 'Jam Kantor',
            'allowed_destinations_text' => '192.168.10.0/24',
            'schedule_enabled' => '1',
            'schedule_days' => ['1', '2', '3', '4', '5'],
            'schedule_start' => '08:00',
            'schedule_end' => '17:00',
        ])
        ->assertRedirect();

    expect(VpnGroup::where('name', 'Jam Kantor')->firstOrFail()->access_schedule)->toBe([
        'days' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '17:00',
    ]);
});

it('enrolls vpn MFA and only flashes the QR payload', function () {
    $client = VpnClient::create([
        'name' => 'Android Test', 'username' => 'android-test', 'common_name' => 'android-test',
        'vpn_ip' => '10.200.0.50', 'status' => 'active', 'certificate_status' => 'active',
    ]);
    $this->mock(OpenVpnService::class, function (MockInterface $mock) use ($client) {
        $mock->shouldReceive('enrollMfa')->once()->withArgs(fn ($value) => $value->is($client))->andReturn([
            'otpauth_uri' => 'otpauth://totp/ManproVPN%3Aandroid-test?secret=ABC123&issuer=ManproVPN',
            'recovery_codes' => ['12345678', '87654321'],
        ]);
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.vpn.clients.mfa.enroll', $client))
        ->assertRedirect()
        ->assertSessionHas('mfa_enrollment.common_name', 'android-test');

    expect($client->fresh()->mfa_enabled_at)->not->toBeNull();
});

it('disables vpn MFA and marks the client as unenrolled', function () {
    $client = VpnClient::create([
        'name' => 'Android Test', 'username' => 'android-test', 'common_name' => 'android-test',
        'vpn_ip' => '10.200.0.50', 'status' => 'active', 'certificate_status' => 'active',
        'mfa_enabled_at' => now(),
    ]);
    $this->mock(OpenVpnService::class, function (MockInterface $mock) use ($client) {
        $mock->shouldReceive('disableMfa')->once()->withArgs(fn ($value) => $value->is($client));
    });

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.vpn.clients.mfa.disable', $client))
        ->assertRedirect();

    expect($client->fresh()->mfa_enabled_at)->toBeNull();
});

it('exports vpn users and provides a bulk import template', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.vpn.users.export'))
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->actingAs($admin)->get(route('admin.vpn.users.import-template'))
        ->assertOk()
        ->assertHeader('content-disposition');
});
