<?php

use App\Models\SecurityAttackLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('shows an attack map and maps private destinations to Semarang', function () {
    Cache::flush();
    config()->set('services.attack_map.ipdata_api_key', 'test-api-key');
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
        'srcip' => '9.9.9.9', 'srcport' => 443, 'dstip' => '192.168.1.10', 'dstport' => 80,
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
        ->assertJsonPath('arcs.0.source_port', 443)
        ->assertJsonPath('arcs.0.destination_port', 80)
        ->assertJsonPath('arcs.0.method', 'GET')
        ->assertJsonPath('arcs.0.url', '/login')
        ->assertJsonPath('arcs.0.destination.city', 'Semarang')
        ->assertJsonPath('arcs.0.destination.is_local', true);
});

it('uses the free ipdata endpoint without an api key', function () {
    Cache::flush();
    config()->set('services.attack_map.ipdata_api_key', null);
    Http::fake([
        'https://ipdata.free/*' => Http::response([
            'ip' => '9.9.9.9',
            'city' => 'New York',
            'country' => 'US',
            'latitude' => 40.7128,
            'longitude' => -74.006,
        ]),
    ]);

    $service = app(\App\Services\AttackIpGeolocationService::class);
    $locations = $service->resolveMany(['9.9.9.9']);

    expect($locations['9.9.9.9']['country_code'])->toBe('US')
        ->and($locations['9.9.9.9']['city'])->toBe('New York')
        ->and($service->diagnostics()['api_key_configured'])->toBeFalse()
        ->and($service->diagnostics()['mode'])->toBe('free_without_key')
        ->and($service->diagnostics()['unresolved'])->toBe(0);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://ipdata.free/9.9.9.9'));
});

it('reports every ip that fails geolocation', function () {
    Cache::flush();
    config()->set('services.attack_map.ipdata_api_key', 'test-api-key');
    Http::fake(['https://api.ipdata.co/*' => Http::response(['message' => 'rate limited'], 429)]);

    $service = app(\App\Services\AttackIpGeolocationService::class);
    $service->resolveMany(['9.9.9.8', '9.9.9.7']);

    expect($service->diagnostics()['unresolved'])->toBe(2)
        ->and($service->diagnostics()['unresolved_ips'])->toBe(['9.9.9.8', '9.9.9.7']);
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
