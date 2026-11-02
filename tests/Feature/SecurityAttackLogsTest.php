<?php

use App\Models\SecurityAttackLog;
use App\Models\User;
use App\Exports\SecurityAttackLogsExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

function securityAttackLogPayload(array $overrides = []): array
{
    return array_merge([
        'device' => 'FGT-EDGE-01',
        'severity' => 'high',
        'attack' => 'SQL Injection',
        'attackid' => '12345',
        'subtype' => 'ips',
        'action' => 'blocked',
        'srcip' => '192.0.2.10',
        'srcport' => 54321,
        'dstip' => '198.51.100.20',
        'dstport' => 443,
        'service' => 'HTTPS',
        'method' => 'GET',
        'url' => '/login',
        'time' => '2026-11-01 21:00:00',
    ], $overrides);
}

beforeEach(function () {
    config()->set('services.security_log.api_key', 'security-test-secret');
});

it('stores FortiGate attack events sent by n8n', function () {
    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/security-logs/events', securityAttackLogPayload())
        ->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.time', '2026-11-01 21:00:00');

    $this->assertDatabaseHas('security_attack_logs', [
        'device' => 'FGT-EDGE-01',
        'severity' => 'high',
        'attack' => 'SQL Injection',
        'attackid' => '12345',
        'srcip' => '192.0.2.10',
        'dstip' => '198.51.100.20',
        'method' => 'GET',
        'url' => '/login',
        'event_time' => '2026-11-01 21:00:00',
    ]);
});

it('rejects security logs without a valid api key', function () {
    $this->postJson('/api/security-logs/events', securityAttackLogPayload())
        ->assertUnauthorized()
        ->assertJsonPath('ok', false);
});

it('reports when the security log api key is not configured', function () {
    config()->set('services.security_log.api_key', '');

    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/security-logs/events', securityAttackLogPayload())
        ->assertServiceUnavailable()
        ->assertJsonPath('ok', false);
});

it('validates required fields ip addresses and ports', function () {
    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/security-logs/events', securityAttackLogPayload([
            'srcip' => 'not-an-ip',
            'dstport' => 70000,
            'attack' => '',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['srcip', 'dstport', 'attack']);
});

it('stores optional http fields as dashes when omitted', function () {
    $payload = securityAttackLogPayload();
    unset($payload['method'], $payload['url']);

    $this->withHeader('X-Security-Log-Api-Key', 'security-test-secret')
        ->postJson('/api/security-logs/events', $payload)
        ->assertCreated();

    $this->assertDatabaseHas('security_attack_logs', [
        'method' => '-',
        'url' => '-',
    ]);
});

it('allows admins to search and delete security logs', function () {
    $matchingLog = SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload(), ['time' => true]),
        'event_time' => '2026-11-01 21:00:00',
    ]);
    SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload([
            'device' => 'FGT-BRANCH-02',
            'severity' => 'low',
            'attack' => 'Port Scan',
        ]), ['time' => true]),
        'event_time' => '2026-11-01 20:00:00',
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get('/admin/log-security?keyword=SQL&severity=high')
        ->assertOk()
        ->assertSee('Log Security')
        ->assertSee('FGT-EDGE-01')
        ->assertDontSee('Port Scan')
        ->assertSee('href="/admin/log-security"', false);

    $this->delete(route('admin.log-security.destroy', $matchingLog))
        ->assertRedirect(route('admin.log-security.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('security_attack_logs', ['id' => $matchingLog->id]);
});

it('restricts security log management to admins', function () {
    $log = SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload(), ['time' => true]),
        'event_time' => '2026-11-01 21:00:00',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'officer']))
        ->get('/admin/log-security')
        ->assertForbidden();

    $this->delete(route('admin.log-security.destroy', $log))
        ->assertForbidden();

    $this->get(route('admin.log-security.export'))
        ->assertForbidden();
});

it('filters security logs by device action source destination and event date range', function () {
    SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload(), ['time' => true]),
        'event_time' => '2026-11-01 21:00:00',
    ]);
    SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload([
            'device' => 'FGT-BRANCH-02',
            'action' => 'detected',
            'attack' => 'Port Scan',
            'srcip' => '203.0.113.40',
            'dstip' => '203.0.113.50',
        ]), ['time' => true]),
        'event_time' => '2026-10-20 10:00:00',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/log-security?device=FGT-EDGE-01&action=blocked&source=192.0.2.10&destination=198.51.100.20&date_from=2026-11-01&date_until=2026-11-01')
        ->assertOk()
        ->assertSee('SQL Injection')
        ->assertDontSee('Port Scan');

    $this->get('/admin/log-security?date_from=2026-11-02&date_until=2026-11-01')
        ->assertSessionHasErrors('date_until');
});

it('exports security logs matching the selected filters', function () {
    SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload(), ['time' => true]),
        'event_time' => '2026-11-01 21:00:00',
    ]);
    SecurityAttackLog::query()->create([
        ...array_diff_key(securityAttackLogPayload([
            'device' => 'FGT-BRANCH-02',
            'attack' => 'Port Scan',
        ]), ['time' => true]),
        'event_time' => '2026-11-01 20:00:00',
    ]);

    Excel::fake();
    $this->travelTo(Carbon::parse('2026-11-02 09:30:00'));

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/log-security/export?device=FGT-EDGE-01&severity=high')
        ->assertOk();

    Excel::assertDownloaded('log-security-20261102-093000.xlsx', function (SecurityAttackLogsExport $export): bool {
        return $export->query()->get()->pluck('device')->all() === ['FGT-EDGE-01'];
    });

    $this->travelBack();
});
