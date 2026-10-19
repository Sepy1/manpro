<?php

use App\Models\User;

test('vendor network hanya dapat membuka halaman map', function () {
    $user = User::factory()->create([
        'role' => 'vendor_network',
        'two_factor_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.map.index'))
        ->assertOk()
        ->assertSee('Peta Kantor Jawa Tengah')
        ->assertDontSee('Project');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertForbidden();
});

test('dashboard umum mengarahkan vendor network ke map', function () {
    $user = User::factory()->create([
        'role' => 'vendor_network',
        'two_factor_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.map.index'));
});
