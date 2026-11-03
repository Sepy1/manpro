<?php

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

it('shows application user activity in the standalone audit log menu', function () {
    $admin = User::factory()->admin()->create();

    UserActivityLog::query()->create([
        'user_id' => $admin->id,
        'activity_type' => UserActivityLog::TYPE_MENU_OPEN,
        'route_name' => 'admin.dashboard',
        'menu_name' => 'Dashboard',
        'url' => 'http://localhost/admin/dashboard',
        'method' => 'GET',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertSee('Audit Log')
        ->assertSee('Audit Aktivitas User Aplikasi')
        ->assertSee('Dashboard')
        ->assertDontSee('Log User')
        ->assertViewHas('logs', fn ($logs) => $logs instanceof LengthAwarePaginator && $logs->perPage() === 50);
});

it('keeps the audit log restricted to admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'officer']))
        ->get(route('admin.audit-log.index'))
        ->assertForbidden();
});

it('redirects the previous log user URL to the audit log', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.manajemen-log-user.index', ['activity_type' => 'login']))
        ->assertRedirect(route('admin.audit-log.index', ['activity_type' => 'login']));
});
