<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an admin to add a managed user', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.manajemen-user.store'), [
            '_form' => 'create',
            'name' => 'User Baru',
            'email' => 'user.baru@example.test',
            'phone' => '081234567890',
            'role' => 'admin',
            'password' => 'password-baru',
            'two_factor_enabled' => '1',
            'can_authorize_extern_cr' => '0',
        ])
        ->assertRedirect(route('admin.manajemen-user.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'User berhasil ditambahkan.');

    $this->assertDatabaseHas('users', [
        'email' => 'user.baru@example.test',
        'phone' => '6281234567890',
        'role' => 'admin',
        'two_factor_enabled' => true,
    ]);
});

it('reopens the add user form and displays validation details after a failed save', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.manajemen-user.index'))
        ->post(route('admin.manajemen-user.store'), [
            '_form' => 'create',
            'name' => 'User Baru',
            'email' => 'bukan-email',
            'role' => 'admin',
            'password' => 'pendek',
            'two_factor_enabled' => '1',
            'can_authorize_extern_cr' => '0',
        ]);

    $response
        ->assertRedirect(route('admin.manajemen-user.index'))
        ->assertSessionHasErrors(['email', 'phone', 'password']);

    $this->get(route('admin.manajemen-user.index'))
        ->assertOk()
        ->assertSee('User belum dapat disimpan:');
});
