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
        ->assertSee('Rose Quartz')
        ->assertSee('Jenis font aplikasi')
        ->assertSee('Ukuran font aplikasi')
        ->assertSee('Segoe UI')
        ->assertSee('Besar');

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'emerald',
        'font_family' => 'segoe',
        'font_size' => 'comfortable',
    ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ApplicationThemeSetting::activeTheme())->toBe('emerald');
    expect(ApplicationThemeSetting::activeFontFamily())->toBe('segoe');
    expect(ApplicationThemeSetting::activeFontSize())->toBe('comfortable');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('root.dataset.appTheme = "emerald"', false)
        ->assertSee('root.dataset.appThemeMode = "dark"', false)
        ->assertSee('root.dataset.appFont = "segoe"', false)
        ->assertSee("root.style.setProperty('--app-font-size', \"87.5%\")", false);

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'daylight',
        'font_family' => 'arial',
        'font_size' => 'large',
    ])
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
        ->put(route('admin.parameter.application-theme.update'), [
            'theme' => 'invalid-theme',
            'font_family' => 'invalid-font',
            'font_size' => 'giant',
        ])
        ->assertSessionHasErrors(['theme', 'font_family', 'font_size']);

    $officer = User::factory()->create(['role' => 'officer']);

    $this->actingAs($officer)
        ->get(route('admin.parameter.application-theme.index'))
        ->assertForbidden();

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'violet',
        'font_family' => 'figtree',
        'font_size' => 'normal',
    ])
        ->assertForbidden();
});
