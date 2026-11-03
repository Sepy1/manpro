<?php

use App\Exports\UserAccessLogsExport;
use App\Models\User;
use App\Models\UserAccessLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

function userAccessLogPayload(array $overrides = []): array
{
    return array_merge([
        'vendor' => 'FortiGate', 'device_name' => 'FortiGate-200F', 'device_id' => 'FG200FT923904242',
        'virtual_domain' => 'root', 'event_type' => 'admin_login_success', 'event_category' => 'admin_login',
        'username' => 'admin', 'auth_server' => null, 'source_ip' => '192.176.1.201',
        'destination_ip' => '192.176.1.240', 'tunnel_ip' => null,
        'source_interface' => 'https(192.176.1.201)', 'destination_interface' => null,
        'method' => 'https', 'action' => 'login', 'status' => 'success', 'original_status' => 'success',
        'reason' => 'none', 'profile' => 'super_admin', 'date' => '2026-11-03', 'time' => '16:11:15',
        'timezone' => '+0700', 'event_time' => '1793697075141498321', 'log_id' => '0100032001',
        'log_description' => 'Admin login successful', 'severity' => 'information',
        'message' => 'Administrator admin logged in successfully from https(192.176.1.201)',
        'raw_log' => 'date=2026-11-03 time=16:11:15 user="admin" action="login" status="success"',
    ], $overrides);
}

beforeEach(fn () => config()->set('services.security_log.api_key', 'security-test-secret'));

it('stores a batch of user access events with the security log api key', function () {
    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/user-access-logs/events', [
            userAccessLogPayload(),
            userAccessLogPayload(['username' => 'operator', 'event_time' => '1793697075141498322']),
        ])
        ->assertCreated()->assertJsonPath('data.count', 2);

    $this->assertDatabaseHas('user_access_logs', [
        'username' => 'admin', 'device_id' => 'FG200FT923904242', 'event_date' => '2026-11-03',
        'event_clock' => '16:11:15', 'raw_log' => 'date=2026-11-03 time=16:11:15 user="admin" action="login" status="success"',
    ]);
    expect(UserAccessLog::query()->count())->toBe(2);
});

it('accepts nullable and omitted user access event fields', function () {
    $nullablePayload = array_fill_keys(array_keys(userAccessLogPayload()), null);

    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/user-access-logs/events', [$nullablePayload, ['vendor' => null]])
        ->assertCreated()
        ->assertJsonPath('data.count', 2);

    expect(UserAccessLog::query()->count())->toBe(2);
    $this->assertDatabaseHas('user_access_logs', [
        'vendor' => null,
        'username' => null,
        'event_date' => null,
        'event_clock' => null,
        'raw_log' => null,
    ]);
});

it('rejects user access events without the security log api key', function () {
    $this->postJson('/api/user-access-logs/events', [userAccessLogPayload()])->assertUnauthorized();
});

it('validates every event in the batch atomically', function () {
    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/user-access-logs/events', [userAccessLogPayload(), userAccessLogPayload(['source_ip' => 'invalid'])])
        ->assertUnprocessable()->assertJsonValidationErrors('events.1.source_ip');
    expect(UserAccessLog::query()->count())->toBe(0);
});

it('lets admins view filter and delete user access logs', function () {
    $log = UserAccessLog::query()->create([
        ...array_diff_key(userAccessLogPayload(), ['date' => true, 'time' => true]),
        'event_date' => '2026-11-03', 'event_clock' => '16:11:15',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/log-access-user?keyword=admin&status=success&device=FortiGate-200F')
        ->assertOk()->assertSee('Log Akses User')->assertSee('192.176.1.201')->assertSee('Export Excel');

    $this->delete(route('admin.log-access-user.destroy', $log))->assertRedirect(route('admin.log-access-user.index'));
    $this->assertDatabaseMissing('user_access_logs', ['id' => $log->id]);
});

it('exports user access logs matching the selected filters', function () {
    UserAccessLog::query()->create([
        ...array_diff_key(userAccessLogPayload(), ['date' => true, 'time' => true]),
        'event_date' => '2026-11-03', 'event_clock' => '16:11:15',
    ]);
    UserAccessLog::query()->create([
        ...array_diff_key(userAccessLogPayload([
            'device_name' => 'FortiGate-Branch',
            'device_id' => 'FG-BRANCH-01',
            'status' => 'failed',
        ]), ['date' => true, 'time' => true]),
        'event_date' => '2026-11-03', 'event_clock' => '16:12:15',
    ]);

    Excel::fake();
    $this->travelTo(Carbon::parse('2026-11-03 21:30:00'));

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/log-access-user/export?device=FortiGate-200F&status=success')
        ->assertOk();

    Excel::assertDownloaded('log-akses-user-20261103-213000.xlsx', function (UserAccessLogsExport $export): bool {
        return $export->query()->get()->pluck('device_name')->all() === ['FortiGate-200F'];
    });

    $this->travelBack();
});

it('restricts the user access log page to admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'officer']));

    $this->get('/admin/log-access-user')->assertForbidden();
    $this->get('/admin/log-access-user/export')->assertForbidden();
});
