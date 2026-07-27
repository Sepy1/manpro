<?php

use App\Enums\ExternCrStatus;
use App\Models\Division;
use App\Models\ExternCr;
use App\Models\ExternCrApiKey;
use App\Models\ExternCrApplication;
use App\Models\ExternCrChangeReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createExternCrFixture(array $overrides = []): ExternCr
{
    $division = Division::query()->create([
        'name' => 'IT',
        'is_active' => true,
    ]);

    $application = ExternCrApplication::query()->create([
        'name' => 'ERP',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $reason = ExternCrChangeReason::query()->create([
        'name' => 'Perbaikan Bug',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    return ExternCr::query()->create(array_merge([
        'nomor' => 'CR-2026-0001',
        'tanggal' => '2026-07-27',
        'daily_sequence' => 1,
        'division_id' => $division->id,
        'extern_cr_application_id' => $application->id,
        'jenis_perubahan' => 'temporary',
        'extern_cr_change_reason_id' => $reason->id,
        'prioritas' => 'sedang',
        'status' => ExternCrStatus::Open,
        'nama' => 'Perubahan form login',
    ], $overrides));
}

it('returns dashboard board payload for external apps', function () {
    $plainKey = Str::random(32);
    ExternCrApiKey::query()->create([
        'name' => 'integration-test',
        'key_hash' => hash('sha256', $plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'is_active' => true,
    ]);

    createExternCrFixture();

    $response = $this->withHeader('X-Extern-Cr-Api-Key', $plainKey)
        ->getJson('/api/cr-eksternal/dashboard');

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('board.title', 'CR Eksternal')
        ->assertJsonStructure([
            'ok',
            'board' => [
                'title',
                'columns' => [
                    ['key', 'title', 'status', 'items'],
                ],
                'status_options',
                'meta' => ['total_items', 'generated_at'],
            ],
        ]);
});

it('updates cr status through api', function () {
    $plainKey = Str::random(32);
    ExternCrApiKey::query()->create([
        'name' => 'integration-test-2',
        'key_hash' => hash('sha256', $plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'is_active' => true,
    ]);

    $cr = createExternCrFixture();

    $response = $this->withHeader('X-Extern-Cr-Api-Key', $plainKey)
        ->patchJson("/api/cr-eksternal/{$cr->id}/status", [
            'status' => ExternCrStatus::VendorDevelopment->value,
            'note' => 'Dipindah oleh aplikasi lain',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('item.status.value', ExternCrStatus::VendorDevelopment->value);

    $this->assertDatabaseHas('extern_crs', [
        'id' => $cr->id,
        'status' => ExternCrStatus::VendorDevelopment->value,
    ]);
});

it('rejects requests without a valid api key', function () {
    $response = $this->getJson('/api/cr-eksternal/dashboard');

    $response->assertUnauthorized();
});
