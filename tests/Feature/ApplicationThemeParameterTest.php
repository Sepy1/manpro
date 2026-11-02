<?php

use App\Models\ApplicationThemeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to view and update the application theme', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.parameter.application-theme.index'))
        ->assertOk()
        ->assertSee('Tema Aplikasi')
        ->assertSee('Midnight Blue')
        ->assertSee('Emerald Forest')
        ->assertSee('Royal Violet')
        ->assertSee('Graphite')
        ->assertSee('Ocean Teal')
        ->assertSee('Daylight Blue')
        ->assertSee('Fresh Mint')
        ->assertSee('Soft Lavender')
        ->assertSee('Warm Sand')
        ->assertSee('Rose Quartz');

    $this->put(route('admin.parameter.application-theme.update'), ['theme' => 'emerald'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ApplicationThemeSetting::activeTheme())->toBe('emerald');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('root.dataset.appTheme = "emerald"', false)
        ->assertSee('root.dataset.appThemeMode = "dark"', false);

    $this->put(route('admin.parameter.application-theme.update'), ['theme' => 'daylight'])
        ->assertRedirect();

    expect(ApplicationThemeSetting::activeMode())->toBe('light');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee("root.classList.toggle('dark', false)", false)
        ->assertSee('root.dataset.appThemeMode = "light"', false);
});

it('validates themes and restricts theme management to admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.parameter.application-theme.update'), ['theme' => 'invalid-theme'])
        ->assertSessionHasErrors('theme');

    $officer = User::factory()->create(['role' => 'officer']);

    $this->actingAs($officer)
        ->get(route('admin.parameter.application-theme.index'))
        ->assertForbidden();

    $this->put(route('admin.parameter.application-theme.update'), ['theme' => 'violet'])
        ->assertForbidden();
});
