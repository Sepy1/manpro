<?php

use App\Models\SecurityAttackLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('shows an attack map and maps private destinations to Semarang', function () {
    Cache::flush();
    Http::fake([
        'https://api.ipdata.co/*' => Http::response([
            'country_name' => 'United States',
            'country_code' => 'US',
            'city' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.006,
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    SecurityAttackLog::query()->create([
        'device' => 'FGT-01', 'severity' => 'critical', 'attack' => 'SQL Injection',
        'attackid' => '1001', 'subtype' => 'ips', 'action' => 'blocked',
        'srcip' => '8.8.8.8', 'srcport' => 443, 'dstip' => '192.168.1.10', 'dstport' => 80,
        'service' => 'HTTP', 'method' => 'GET', 'url' => '/login', 'event_time' => now()->format('Y-m-d H:i:s'),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attack-map.index'))
        ->assertOk()
        ->assertSee('Attack Map')
        ->assertSee('Peta sumber dan tujuan serangan');

    $this->getJson(route('admin.attack-map.data', ['hours' => 24]))
        ->assertOk()
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.blocked', 1)
        ->assertJsonPath('arcs.0.source.country', 'United States')
        ->assertJsonPath('arcs.0.destination.city', 'Semarang')
        ->assertJsonPath('arcs.0.destination.is_local', true);
});

it('restricts the attack map to admins', function () {
    $officer = User::factory()->create(['role' => 'officer']);
    $this->actingAs($officer)->get(route('admin.attack-map.index'))->assertForbidden();
    $this->getJson(route('admin.attack-map.data'))->assertForbidden();
});

it('keeps seeded demo sources mapped when the geolocation cache is empty', function () {
    Cache::flush();
    Http::fake();

    $locations = app(\App\Services\AttackIpGeolocationService::class)
        ->resolveMany(['8.8.8.8', '192.168.10.10']);

    expect($locations['8.8.8.8']['country'])->toBe('United States')
        ->and($locations['8.8.8.8']['city'])->toBe('New York')
        ->and($locations['192.168.10.10']['city'])->toBe('Semarang');

    Http::assertNothingSent();
});
