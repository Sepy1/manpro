<?php

use App\Models\VpnClient;
use App\Services\OpenVpnService;
use Tests\TestCase;

uses(TestCase::class);

it('reads topology explicitly before writing a subnet CCD', function () {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'openvpn-test-'.bin2hex(random_bytes(4));
    mkdir($root.DIRECTORY_SEPARATOR.'ccd', 0777, true);
    file_put_contents($root.DIRECTORY_SEPARATOR.'server.conf', "server 10.200.0.0 255.255.255.0\ntopology subnet\n");
    config(['openvpn.server_config' => $root.DIRECTORY_SEPARATOR.'server.conf', 'openvpn.ccd_dir' => $root.DIRECTORY_SEPARATOR.'ccd']);

    $client = new VpnClient(['common_name' => 'laptop-erik', 'vpn_ip' => '10.200.0.10']);
    $service = new OpenVpnService;
    expect($service->topology())->toBe('subnet');
    $service->writeCcd($client);
    expect(file_get_contents($root.DIRECTORY_SEPARATOR.'ccd'.DIRECTORY_SEPARATOR.'laptop-erik'))->toBe("ifconfig-push 10.200.0.10 255.255.255.0\n");

    unlink($root.DIRECTORY_SEPARATOR.'ccd'.DIRECTORY_SEPARATOR.'laptop-erik');
    unlink($root.DIRECTORY_SEPARATOR.'server.conf');
    rmdir($root.DIRECTORY_SEPARATOR.'ccd');
    rmdir($root);
});

it('refuses to assume topology when directive is absent', function () {
    $path = tempnam(sys_get_temp_dir(), 'ovpn-');
    file_put_contents($path, "server 10.200.0.0 255.255.255.0\n");
    config(['openvpn.server_config' => $path]);
    expect(fn () => (new OpenVpnService)->topology())->toThrow(RuntimeException::class, 'Topology OpenVPN tidak ditemukan');
    unlink($path);
});

it('refuses subnet style CCD for net30 topology', function () {
    $path = tempnam(sys_get_temp_dir(), 'ovpn-');
    file_put_contents($path, "topology net30\n");
    config(['openvpn.server_config' => $path]);
    $client = new VpnClient(['common_name' => 'device-1', 'vpn_ip' => '10.200.0.10']);
    expect(fn () => (new OpenVpnService)->writeCcd($client))->toThrow(RuntimeException::class, "Topology 'net30' terdeteksi");
    unlink($path);
});

it('builds an MFA profile compatible with OpenVPN Connect', function () {
    $profile = <<<'OVPN'
client
dev tun
proto udp
remote 103.131.217.51 1194
resolve-retry infinite
persist-key
persist-tun
auth-retry interact
auth-nocache
data-ciphers AES-256-GCM:AES-128-GCM
<ca>
certificate
</ca>
OVPN;

    $normalized = (new OpenVpnService)->normalizeClientProfile($profile);

    expect($normalized)
        ->toContain("remote 103.131.217.51 1194\nauth-user-pass\n")
        ->not->toContain('auth-nocache')
        ->not->toContain('auth-retry')
        ->not->toContain('resolve-retry')
        ->not->toContain('persist-key')
        ->not->toContain('persist-tun')
        ->not->toContain('data-ciphers');
});

it('does not duplicate auth-user-pass in an existing profile', function () {
    $normalized = (new OpenVpnService)->normalizeClientProfile("client\nauth-user-pass\nremote vpn.example.test 1194\n");

    expect(substr_count($normalized, 'auth-user-pass'))->toBe(1);
});
