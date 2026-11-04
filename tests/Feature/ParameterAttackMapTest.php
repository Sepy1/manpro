<?php

use App\Models\AttackMapSetting;
use App\Models\Kantor;
use App\Models\User;
use App\Services\AttackIpGeolocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('allows an admin to configure central ips and headquarters', function () {
    $admin = User::factory()->admin()->create();
    $office = Kantor::query()->create([
        'kode_kantor' => 'KP-01',
        'nama_kantor' => 'Kantor Pusat Utama',
        'latitude' => -7.0051,
        'longitude' => 110.4381,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.parameter.attack-map.update'), [
            'headquarters_kantor_id' => $office->id,
            'central_ips' => "203.0.113.10\n10.20.0.0/16",
        ])
        ->assertRedirect();

    $setting = AttackMapSetting::query()->firstOrFail();
    expect($setting->headquarters_kantor_id)->toBe($office->id)
        ->and($setting->central_ip_ranges)->toBe(['203.0.113.10', '10.20.0.0/16']);
});

it('maps configured central ips to headquarters without calling ipdata', function () {
    $office = Kantor::query()->create([
        'kode_kantor' => 'KP-02',
        'nama_kantor' => 'Kantor Pusat Jakarta',
        'latitude' => -6.2000,
        'longitude' => 106.8166,
        'is_active' => true,
    ]);
    AttackMapSetting::query()->create([
        'headquarters_kantor_id' => $office->id,
        'central_ip_ranges' => ['203.0.113.0/24'],
    ]);
    Http::fake();

    $locations = app(AttackIpGeolocationService::class)->resolveMany(['203.0.113.10']);

    expect($locations['203.0.113.10']['city'])->toBe('Kantor Pusat Jakarta')
        ->and($locations['203.0.113.10']['latitude'])->toBe(-6.2)
        ->and($locations['203.0.113.10']['longitude'])->toBe(106.8166)
        ->and($locations['203.0.113.10']['is_local'])->toBeTrue();
    Http::assertNothingSent();
});

it('rejects an invalid central ip entry', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.parameter.attack-map.index'))
        ->put(route('admin.parameter.attack-map.update'), ['central_ips' => 'bukan-ip'])
        ->assertRedirect(route('admin.parameter.attack-map.index'))
        ->assertSessionHasErrors('central_ips');
});
