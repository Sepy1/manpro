<?php

namespace Database\Seeders;

use App\Models\SecurityAttackLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class AttackMapDummySeeder extends Seeder
{
    public function run(): void
    {
        $attacks = [
            ['US', 'United States', 'New York', '8.8.8.8', 40.7128, -74.0060, 'critical', 'SQL Injection', 18],
            ['CN', 'China', 'Beijing', '1.202.0.1', 39.9042, 116.4074, 'high', 'Web Access Attack', 16],
            ['RU', 'Russia', 'Moscow', '5.255.255.70', 55.7558, 37.6173, 'critical', 'Botnet Communication', 14],
            ['DE', 'Germany', 'Frankfurt', '80.156.86.78', 50.1109, 8.6821, 'medium', 'Port Scan', 12],
            ['GB', 'United Kingdom', 'London', '81.2.69.142', 51.5074, -0.1278, 'high', 'Brute Force Login', 10],
            ['JP', 'Japan', 'Tokyo', '210.173.160.27', 35.6762, 139.6503, 'medium', 'Directory Traversal', 9],
            ['SG', 'Singapore', 'Singapore', '103.6.117.2', 1.3521, 103.8198, 'high', 'Command Injection', 8],
            ['BR', 'Brazil', 'São Paulo', '200.160.2.3', -23.5505, -46.6333, 'medium', 'Malware Download', 7],
            ['AU', 'Australia', 'Sydney', '203.50.2.71', -33.8688, 151.2093, 'low', 'Suspicious HTTP Request', 6],
            ['ZA', 'South Africa', 'Johannesburg', '196.25.1.1', -26.2041, 28.0473, 'high', 'Credential Stuffing', 5],
        ];

        foreach ($attacks as $index => [$code, $country, $city, $sourceIp, $latitude, $longitude, $severity, $attack, $count]) {
            Cache::put('attack-map:geoip:ipdata:v1:'.sha1($sourceIp), [
                'country' => $country,
                'country_code' => $code,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'is_local' => false,
            ], now()->addDays(30));

            for ($sequence = 1; $sequence <= $count; $sequence++) {
                $occurredAt = now()->subMinutes(($index * 4) + $sequence);
                SecurityAttackLog::query()->updateOrCreate(
                    ['attackid' => sprintf('DUMMY-MAP-%s-%03d', $code, $sequence)],
                    [
                        'device' => 'FGT-SEMARANG-01',
                        'severity' => $severity,
                        'attack' => $attack,
                        'subtype' => 'ips',
                        'action' => $sequence % 4 === 0 ? 'detected' : 'blocked',
                        'srcip' => $sourceIp,
                        'srcport' => 1024 + $sequence,
                        'dstip' => '192.168.10.'.(10 + $index),
                        'dstport' => in_array($attack, ['Brute Force Login', 'Credential Stuffing'], true) ? 22 : 443,
                        'service' => 'HTTPS',
                        'method' => 'POST',
                        'url' => '/dummy-attack/'.strtolower($code),
                        'event_time' => $occurredAt->format('Y-m-d H:i:s'),
                        'created_at' => $occurredAt,
                        'updated_at' => $occurredAt,
                    ],
                );
            }
        }
    }
}
