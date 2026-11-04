<?php

use App\Models\ApplicationThemeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows admins to view and update the application theme', function () {
    Storage::fake('public');
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
        ->assertSee('Elastic')
        ->assertSee('Fresh Mint')
        ->assertSee('Soft Lavender')
        ->assertSee('Warm Sand')
        ->assertSee('Rose Quartz')
        ->assertSee('Jenis font aplikasi')
        ->assertSee('Ukuran font aplikasi')
        ->assertSee('Ketebalan font aplikasi')
        ->assertSee('Segoe UI')
        ->assertSee('Besar');

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'emerald',
        'font_family' => 'segoe',
        'font_size' => 'comfortable',
        'font_weight' => 'bold',
        'logo' => UploadedFile::fake()->image('logo.png', 640, 180),
        'login_logo' => UploadedFile::fake()->image('login-logo.png', 512, 256),
    ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ApplicationThemeSetting::activeTheme())->toBe('emerald');
    expect(ApplicationThemeSetting::activeFontFamily())->toBe('segoe');
    expect(ApplicationThemeSetting::activeFontSize())->toBe('comfortable');
    expect(ApplicationThemeSetting::activeFontWeight())->toBe('bold');
    expect(ApplicationThemeSetting::activeLogoPath())->not->toBeNull();
    expect(ApplicationThemeSetting::activeLoginLogoPath())->not->toBeNull();
    Storage::disk('public')->assertExists(ApplicationThemeSetting::activeLogoPath());
    Storage::disk('public')->assertExists(ApplicationThemeSetting::activeLoginLogoPath());

    $this->get(route('admin.parameter.application-theme.logo'))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $this->get(route('branding.login-logo'))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('root.dataset.appTheme = "emerald"', false)
        ->assertSee('root.dataset.appThemeMode = "dark"', false)
        ->assertSee('root.dataset.appFont = "segoe"', false)
        ->assertSee("root.style.setProperty('--app-font-size', \"87.5%\")", false)
        ->assertSee('root.dataset.appFontWeight = "bold"', false)
        ->assertSee('Logo aplikasi')
        ->assertSee("root.style.setProperty('--app-font-weight', \"700\")", false);

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'daylight',
        'font_family' => 'arial',
        'font_size' => 'large',
        'font_weight' => 'normal',
    ])
        ->assertRedirect();

    expect(ApplicationThemeSetting::activeMode())->toBe('light');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee("root.classList.toggle('dark', false)", false)
        ->assertSee('root.dataset.appThemeMode = "light"', false);

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'elastic',
        'font_family' => 'figtree',
        'font_size' => 'normal',
        'font_weight' => 'normal',
    ])->assertRedirect();

    expect(ApplicationThemeSetting::activeTheme())->toBe('elastic');
    expect(ApplicationThemeSetting::activeMode())->toBe('light');
});

it('validates themes and restricts theme management to admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.parameter.application-theme.update'), [
            'theme' => 'invalid-theme',
            'font_family' => 'invalid-font',
            'font_size' => 'giant',
            'font_weight' => 'heavy',
        ])
        ->assertSessionHasErrors(['theme', 'font_family', 'font_size', 'font_weight']);

    $officer = User::factory()->create(['role' => 'officer']);

    $this->actingAs($officer)
        ->get(route('admin.parameter.application-theme.index'))
        ->assertForbidden();

    $this->put(route('admin.parameter.application-theme.update'), [
        'theme' => 'violet',
        'font_family' => 'figtree',
        'font_size' => 'normal',
        'font_weight' => 'normal',
    ])
        ->assertForbidden();
});
