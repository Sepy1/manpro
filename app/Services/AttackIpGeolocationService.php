<?php

namespace App\Services;

use App\Models\AttackMapSetting;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

class AttackIpGeolocationService
{
    private array $diagnostics = [];

    private const DEMO_LOCATIONS = [
        '8.8.8.8' => ['country' => 'United States', 'country_code' => 'US', 'city' => 'New York', 'latitude' => 40.7128, 'longitude' => -74.0060],
        '1.202.0.1' => ['country' => 'China', 'country_code' => 'CN', 'city' => 'Beijing', 'latitude' => 39.9042, 'longitude' => 116.4074],
        '5.255.255.70' => ['country' => 'Russia', 'country_code' => 'RU', 'city' => 'Moscow', 'latitude' => 55.7558, 'longitude' => 37.6173],
        '80.156.86.78' => ['country' => 'Germany', 'country_code' => 'DE', 'city' => 'Frankfurt', 'latitude' => 50.1109, 'longitude' => 8.6821],
        '81.2.69.142' => ['country' => 'United Kingdom', 'country_code' => 'GB', 'city' => 'London', 'latitude' => 51.5074, 'longitude' => -0.1278],
        '210.173.160.27' => ['country' => 'Japan', 'country_code' => 'JP', 'city' => 'Tokyo', 'latitude' => 35.6762, 'longitude' => 139.6503],
        '103.6.117.2' => ['country' => 'Singapore', 'country_code' => 'SG', 'city' => 'Singapore', 'latitude' => 1.3521, 'longitude' => 103.8198],
        '200.160.2.3' => ['country' => 'Brazil', 'country_code' => 'BR', 'city' => 'São Paulo', 'latitude' => -23.5505, 'longitude' => -46.6333],
        '203.50.2.71' => ['country' => 'Australia', 'country_code' => 'AU', 'city' => 'Sydney', 'latitude' => -33.8688, 'longitude' => 151.2093],
        '196.25.1.1' => ['country' => 'South Africa', 'country_code' => 'ZA', 'city' => 'Johannesburg', 'latitude' => -26.2041, 'longitude' => 28.0473],
    ];

    public function resolveMany(array $ips): array
    {
        $resolved = [];
        $missing = [];
        $unresolved = [];
        $apiKey = trim((string) config('services.attack_map.ipdata_api_key', ''));
        $validIps = array_values(array_unique(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP))));
        $centralConfiguration = AttackMapSetting::configuration();
        $centralIpRanges = $centralConfiguration['central_ip_ranges'];
        $centralLocation = $centralConfiguration['location'];

        foreach ($validIps as $ip) {
            if ($this->isLocal($ip) || ($centralIpRanges !== [] && IpUtils::checkIp($ip, $centralIpRanges))) {
                $resolved[$ip] = $centralLocation;
                continue;
            }
            if (isset(self::DEMO_LOCATIONS[$ip])) {
                $resolved[$ip] = [...self::DEMO_LOCATIONS[$ip], 'is_local' => false];
                continue;
            }
            $cacheKey = $this->cacheKey($ip);
            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    $resolved[$ip] = $cached;
                } else {
                    $unresolved[] = $ip;
                }
                continue;
            }
            $missing[] = $ip;
        }

        $endpoint = $apiKey === ''
            ? (string) config('services.attack_map.geoip_free_endpoint', 'https://ipdata.free/{ip}')
            : (string) config('services.attack_map.geoip_endpoint', 'https://api.ipdata.co/{ip}');
        $query = [];
        if ($apiKey !== '') {
            $query['fields'] = 'ip,city,country_name,country_code,latitude,longitude';
            $query['api-key'] = $apiKey;
        }
        foreach (array_chunk($missing, 25) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $ip) => $pool->as($ip)->acceptJson()->timeout(5)->get(str_replace('{ip}', rawurlencode($ip), $endpoint), $query),
                $chunk,
            ));

            foreach ($chunk as $ip) {
                $response = $responses[$ip] ?? null;
                if (! $response?->successful()) {
                    Cache::put($this->cacheKey($ip), false, now()->addMinutes(5));
                    $unresolved[] = $ip;
                    continue;
                }
                $data = $response->json();
                if (! is_numeric($data['latitude'] ?? null) || ! is_numeric($data['longitude'] ?? null)) {
                    Cache::put($this->cacheKey($ip), false, now()->addMinutes(5));
                    $unresolved[] = $ip;
                    continue;
                }
                $countryCode = strtoupper((string) ($data['country_code'] ?? $data['country'] ?? ''));
                $location = [
                    'country' => $this->countryName((string) ($data['country_name'] ?? ''), $countryCode),
                    'country_code' => $countryCode,
                    'city' => (string) ($data['city'] ?? ''),
                    'latitude' => (float) $data['latitude'],
                    'longitude' => (float) $data['longitude'],
                    'is_local' => false,
                ];
                Cache::put($this->cacheKey($ip), $location, now()->addDays(30));
                $resolved[$ip] = $location;
            }
        }

        $unresolved = array_values(array_unique(array_diff($unresolved, array_keys($resolved))));
        $this->diagnostics = [
            'requested' => count($validIps),
            'resolved' => count($resolved),
            'unresolved' => count($unresolved),
            'unresolved_ips' => $unresolved,
            'api_key_configured' => $apiKey !== '',
            'mode' => $apiKey === '' ? 'free_without_key' : 'api_key',
            'message' => $unresolved === []
                ? null
                : 'Sebagian koordinat gagal dipetakan. Periksa koneksi server ke ipdata.free, batas kuota harian, dan log aplikasi.',
        ];

        if ($unresolved !== [] && Cache::add('attack-map:geoip:warning', true, now()->addMinutes(10))) {
            Log::warning('Attack Map gagal memetakan sebagian alamat IP.', $this->diagnostics);
        }

        return $resolved;
    }

    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    private function isLocal(string $ip): bool
    {
        return IpUtils::checkIp($ip, [
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
            '127.0.0.0/8', '169.254.0.0/16', '::1/128', 'fc00::/7', 'fe80::/10',
        ]);
    }

    private function cacheKey(string $ip): string
    {
        return 'attack-map:geoip:ipdata:v3:'.sha1($ip);
    }

    private function countryName(string $countryName, string $countryCode): string
    {
        if ($countryName !== '') {
            return $countryName;
        }
        if ($countryCode !== '' && class_exists(\Locale::class)) {
            return \Locale::getDisplayRegion('-'.$countryCode, 'en') ?: $countryCode;
        }

        return $countryCode !== '' ? $countryCode : 'Unknown';
    }
}
