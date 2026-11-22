<?php

namespace App\Services;

use App\Models\VpnClient;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class OpenVpnService
{
    public function topology(): string
    {
        if ($this->isRemote()) {
            $topology = trim($this->remote('management', ['topology'])->output());
            if (! in_array($topology, ['subnet', 'net30', 'p2p'], true)) {
                throw new RuntimeException('Topology OpenVPN remote tidak ditemukan atau ambigu.');
            }

            return $topology;
        }

        $path = config('openvpn.server_config');
        if (! is_readable($path)) {
            throw new RuntimeException("Konfigurasi OpenVPN tidak dapat dibaca: {$path}");
        }

        $topologies = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim(preg_replace('/\s*[#;].*$/', '', $line));
            if (preg_match('/^topology\s+(subnet|net30|p2p)\s*$/i', $line, $match)) {
                $topologies[] = strtolower($match[1]);
            }
        }
        $topologies = array_values(array_unique($topologies));
        if (count($topologies) !== 1) {
            throw new RuntimeException('Topology OpenVPN tidak ditemukan atau ambigu. Tetapkan satu directive topology pada server.conf sebelum mengelola CCD.');
        }

        return $topologies[0];
    }

    public function writeCcd(VpnClient $client): void
    {
        $topology = $this->topology();
        if ($topology !== 'subnet') {
            throw new RuntimeException("Topology '{$topology}' terdeteksi. Alokasi /24 saat ini hanya menulis CCD topology subnet; konfigurasi tidak diubah untuk mencegah assignment yang keliru.");
        }
        $this->assertSafeCommonName($client->common_name);
        $this->assertManagedIp($client->vpn_ip);
        if ($this->isRemote()) {
            $this->remote('management', ['write-ccd', $client->common_name, $client->vpn_ip]);

            return;
        }
        $directory = config('openvpn.ccd_dir');
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Direktori CCD tidak dapat dibuat: {$directory}");
        }
        $target = $directory.DIRECTORY_SEPARATOR.$client->common_name;
        if (@file_put_contents($target, "ifconfig-push {$client->vpn_ip} 255.255.255.0\n", LOCK_EX) === false) {
            throw new RuntimeException("Gagal menulis CCD: {$target}");
        }
        @chmod($target, 0640);
    }

    public function deleteCcd(VpnClient $client): void
    {
        $this->assertSafeCommonName($client->common_name);
        if ($this->isRemote()) {
            $this->remote('management', ['delete-ccd', $client->common_name]);

            return;
        }
        $target = config('openvpn.ccd_dir').DIRECTORY_SEPARATOR.$client->common_name;
        if (is_file($target) && ! @unlink($target)) {
            throw new RuntimeException("Gagal menghapus CCD: {$target}");
        }
    }

    public function generateCertificate(VpnClient $client): void
    {
        $this->assertSafeCommonName($client->common_name);
        if ($this->isRemote()) {
            $this->remote('pki', ['generate-certificate', $client->common_name]);

            return;
        }
        $this->run(['./easyrsa', '--batch', 'build-client-full', $client->common_name, 'nopass'], config('openvpn.easy_rsa_dir'));
    }

    public function revokeCertificate(VpnClient $client): void
    {
        $this->assertSafeCommonName($client->common_name);
        if ($this->isRemote()) {
            $this->remote('pki', ['revoke-certificate', $client->common_name]);
            $profile = $this->profilePath($client);
            if (is_file($profile)) {
                File::delete($profile);
            }

            return;
        }
        $dir = config('openvpn.easy_rsa_dir');
        $this->run(['./easyrsa', '--batch', 'revoke', $client->common_name], $dir);
        $this->run(['./easyrsa', 'gen-crl'], $dir);
    }

    public function generateProfile(VpnClient $client): string
    {
        $this->assertSafeCommonName($client->common_name);
        if ($this->isRemote()) {
            $profile = $this->remote('profile', ['export-profile', $client->common_name])->output();
            if (! str_contains($profile, '<key>') || ! str_contains($profile, '<tls-crypt>')) {
                throw new RuntimeException('Profil OpenVPN remote tidak lengkap.');
            }
            $profile = $this->normalizeClientProfile($profile);
            $outputDir = config('openvpn.client_output_dir');
            File::ensureDirectoryExists($outputDir, 0700);
            $path = $this->profilePath($client);
            File::put($path, $profile);
            @chmod($path, 0600);

            return $path;
        }
        $template = config('openvpn.client_template');
        $pki = rtrim(config('openvpn.pki_dir'), '/\\');
        $files = [
            'template' => $template,
            'ca' => "{$pki}/ca.crt",
            'cert' => "{$pki}/issued/{$client->common_name}.crt",
            'key' => "{$pki}/private/{$client->common_name}.key",
        ];
        foreach ($files as $label => $path) {
            if (! is_readable($path)) {
                throw new RuntimeException("Berkas {$label} tidak dapat dibaca: {$path}");
            }
        }
        $outputDir = config('openvpn.client_output_dir');
        File::ensureDirectoryExists($outputDir, 0700);
        $profile = rtrim(file_get_contents($template))."\n\n";
        foreach (['ca' => 'ca', 'cert' => 'cert', 'key' => 'key'] as $key => $tag) {
            $profile .= "<{$tag}>\n".trim(file_get_contents($files[$key]))."\n</{$tag}>\n";
        }
        $profile = $this->normalizeClientProfile($profile);
        $path = $outputDir.DIRECTORY_SEPARATOR.$client->common_name.'.ovpn';
        File::put($path, $profile);
        @chmod($path, 0600);

        return $path;
    }

    public function normalizeClientProfile(string $profile): string
    {
        // OpenVPN Connect (OpenVPN 3) rejects these OpenVPN Community options
        // during profile import. The server still controls cipher negotiation.
        $profile = preg_replace(
            '/^[ \t]*(?:auth-nocache|auth-retry|resolve-retry|persist-key|persist-tun|data-ciphers)\b[^\r\n]*(?:\R|$)/mi',
            '',
            $profile
        ) ?? $profile;

        if (! preg_match('/^[ \t]*auth-user-pass(?:[ \t].*)?$/mi', $profile)) {
            $replacementCount = 0;
            $profile = preg_replace(
                '/^(remote\s+[^\r\n]+\R)/mi',
                "$1auth-user-pass\n",
                $profile,
                1,
                $replacementCount
            ) ?? $profile;

            if ($replacementCount === 0) {
                $profile = "auth-user-pass\n".$profile;
            }
        }

        return rtrim($profile)."\n";
    }

    /** @return array{otpauth_uri:string,recovery_codes:array<int,string>} */
    public function enrollMfa(VpnClient $client): array
    {
        $this->assertSafeCommonName($client->common_name);
        if (! $this->isRemote()) {
            throw new RuntimeException('Enrollment MFA hanya tersedia melalui koneksi SSH ke server OpenVPN.');
        }

        $payload = json_decode($this->remote('mfa', ['enroll', $client->common_name])->output(), true);
        if (! is_array($payload)
            || ! is_string($payload['otpauth_uri'] ?? null)
            || ! str_starts_with($payload['otpauth_uri'], 'otpauth://totp/')
            || ! is_array($payload['recovery_codes'] ?? null)) {
            throw new RuntimeException('Respons enrollment MFA dari server tidak valid.');
        }

        return [
            'otpauth_uri' => $payload['otpauth_uri'],
            'recovery_codes' => array_values(array_map('strval', $payload['recovery_codes'])),
        ];
    }

    public function disableMfa(VpnClient $client): void
    {
        $this->assertSafeCommonName($client->common_name);
        if (! $this->isRemote()) {
            throw new RuntimeException('Pengelolaan MFA hanya tersedia melalui koneksi SSH ke server OpenVPN.');
        }

        $this->remote('mfa', ['disable', $client->common_name]);
    }

    public function profilePath(VpnClient $client): string
    {
        return rtrim(config('openvpn.client_output_dir'), '/\\').DIRECTORY_SEPARATOR.$client->common_name.'.ovpn';
    }

    public function serverStatus(): array
    {
        try {
            $result = $this->isRemote()
                ? $this->remote('service', ['status'])
                : Process::timeout(5)->run(['systemctl', 'is-active', config('openvpn.service')]);

            return ['active' => $result->successful() && trim($result->output()) === 'active', 'label' => trim($result->output() ?: $result->errorOutput()) ?: 'unknown'];
        } catch (\Throwable $e) {
            return ['active' => false, 'label' => 'tidak tersedia'];
        }
    }

    public function restartServer(): void
    {
        if ($this->isRemote()) {
            $this->remote('service', ['restart']);

            return;
        }
        $this->run(['systemctl', 'restart', config('openvpn.service')], '/');
    }

    public function syncAccessPolicy(VpnClient $client): void
    {
        $client->loadMissing('group');
        $this->assertManagedIp($client->vpn_ip);
        $destinations = $client->status === 'active' ? $client->effectiveAllowedDestinations() : [];

        if (! $this->isRemote()) {
            return;
        }

        if ($client->status !== 'active' || ! $client->isWithinAccessSchedule()) {
            $this->remote('policy', ['clear', $client->vpn_ip]);

            return;
        }

        if ($destinations === []) {
            // A non-routable sentinel produces an effective default-deny policy
            // with the wrapper's final REJECT rule, without granting a destination.
            $this->remote('policy', ['apply', $client->vpn_ip, '0.0.0.0/32']);

            return;
        }

        foreach ($destinations as $destination) {
            $this->assertDestination($destination);
        }
        $this->remote('policy', ['apply', $client->vpn_ip, ...$destinations]);
    }

    /** @return array<int, array{common_name:string,real_ip:?string,vpn_ip:?string,bytes_received:int,bytes_sent:int,connected_at:\Illuminate\Support\Carbon}> */
    public function connectedClients(): array
    {
        if ($this->isRemote()) {
            $lines = preg_split('/\R/', $this->remote('management', ['connections'])->output());
        } else {
            $path = config('openvpn.status_log');
            if (! is_readable($path)) {
                throw new RuntimeException("Status log OpenVPN tidak dapat dibaca: {$path}");
            }
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        }
        $clients = [];
        foreach ($lines as $line) {
            $fields = str_getcsv($line);
            if (($fields[0] ?? null) !== 'CLIENT_LIST' || empty($fields[1])) {
                continue;
            }
            $real = $fields[2] ?? null;
            $real = preg_replace('/^(?:udp|tcp)[46]:/i', '', (string) $real);
            if ($real && preg_match('/^(.+):\d+$/', $real, $match)) {
                $real = trim($match[1], '[]');
            }
            $timestamp = filter_var($fields[8] ?? null, FILTER_VALIDATE_INT);
            $clients[] = [
                'common_name' => $fields[1], 'real_ip' => filter_var($real, FILTER_VALIDATE_IP) ? $real : null,
                'vpn_ip' => filter_var($fields[3] ?? null, FILTER_VALIDATE_IP) ? $fields[3] : null,
                'bytes_received' => (int) ($fields[5] ?? 0), 'bytes_sent' => (int) ($fields[6] ?? 0),
                'connected_at' => $timestamp ? \Illuminate\Support\Carbon::createFromTimestamp($timestamp) : now(),
            ];
        }

        return $clients;
    }

    public function firewallRuleCount(): int
    {
        try {
            if ($this->isRemote()) {
                return (int) trim($this->remote('management', ['firewall-count'])->output());
            }
            $result = Process::timeout(5)->run(['iptables', '-S']);

            return $result->successful() ? count(preg_grep('/^-A\s/', preg_split('/\R/', $result->output()))) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function run(array $command, string $cwd): ProcessResult
    {
        $result = Process::path($cwd)->timeout(config('openvpn.command_timeout'))->run($command);
        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Perintah OpenVPN gagal.');
        }

        return $result;
    }

    private function isRemote(): bool
    {
        return config('openvpn.connection') === 'ssh';
    }

    private function remote(string $commandKey, array $arguments): ProcessResult
    {
        $ssh = config('openvpn.ssh');
        $command = config("openvpn.remote_commands.{$commandKey}");
        foreach (['host', 'user', 'key', 'known_hosts'] as $required) {
            if (blank($ssh[$required] ?? null)) {
                throw new RuntimeException("Konfigurasi OPENVPN_SSH_{$required} belum diisi.");
            }
        }
        foreach ($arguments as $argument) {
            if (! preg_match('/\A[A-Za-z0-9_.:\/-]+\z/', (string) $argument)) {
                throw new RuntimeException('Argumen perintah remote tidak aman.');
            }
        }
        $target = $ssh['user'].'@'.$ssh['host'];
        $sshCommand = [
            'ssh', '-i', $ssh['key'], '-p', (string) $ssh['port'],
            '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile='.$ssh['known_hosts'],
            '-o', 'ConnectTimeout='.(int) $ssh['connect_timeout'],
            '--', $target, 'sudo', $command, ...$arguments,
        ];
        $result = Process::timeout(config('openvpn.command_timeout'))->run($sshCommand);
        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Perintah OpenVPN remote gagal.');
        }

        return $result;
    }

    private function assertSafeCommonName(string $cn): void
    {
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,127}\z/', $cn)) {
            throw new RuntimeException('Common Name tidak aman untuk nama berkas/perintah.');
        }
    }

    private function assertManagedIp(string $ip): void
    {
        $long = ip2long($ip);
        $server = ip2long(config('openvpn.server_ip'));
        $network = ip2long('10.200.0.0');
        if ($long === false || $long <= $network || $long >= ip2long('10.200.0.255') || $long === $server) {
            throw new RuntimeException('IP harus berupa alamat host 10.200.0.0/24, bukan IP server, network, atau broadcast.');
        }
    }

    private function assertDestination(string $destination): void
    {
        [$ip, $prefix] = array_pad(explode('/', $destination, 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix < 0 || (int) $prefix > 32))) {
            throw new RuntimeException("Allowed destination tidak valid: {$destination}");
        }
    }
}
