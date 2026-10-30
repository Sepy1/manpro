<?php

use App\Exports\BackupMonitoringEventsExport;
use App\Models\BackupMonitoringEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

function backupMonitoringPayload(array $overrides = []): array
{
    return array_merge([
        'tanggal' => '2026-10-30 21:30:00',
        'nama_db' => 'db_core',
        'ukuran' => 1250.50,
        'status' => 'SUCCESS',
        'lokasi_file' => '/backup/20261030/db_core-20261030.sql.gz',
        'server' => 'DB-SERVER-01',
    ], $overrides);
}

beforeEach(function () {
    config()->set('services.backup_monitoring.api_key', 'backup-test-secret');
});

it('stores backup events sent by an external server', function () {
    $this->withHeader('X-Backup-Api-Key', 'backup-test-secret')
        ->postJson('/api/backup-monitoring/events', backupMonitoringPayload())
        ->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.tanggal', '2026-10-30 21:30:00');

    $this->assertDatabaseHas('backup_monitoring_events', [
        'tanggal' => '2026-10-30 21:30:00',
        'nama_db' => 'db_core',
        'ukuran' => '1250.50',
        'status' => 'SUCCESS',
        'lokasi_file' => '/backup/20261030/db_core-20261030.sql.gz',
        'server' => 'DB-SERVER-01',
    ]);
});

it('rejects backup events without a valid api key', function () {
    $this->postJson('/api/backup-monitoring/events', backupMonitoringPayload())
        ->assertUnauthorized()
        ->assertJsonPath('ok', false);
});

it('validates required event fields and date format', function () {
    $this->withHeader('X-Backup-Api-Key', 'backup-test-secret')
        ->postJson('/api/backup-monitoring/events', backupMonitoringPayload([
            'tanggal' => '30-10-2026 21:30',
            'ukuran' => -1,
            'server' => '',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal', 'ukuran', 'server']);
});

it('shows received backup events to admins only', function () {
    $event = BackupMonitoringEvent::query()->create(backupMonitoringPayload());

    $response = $this->actingAs(\App\Models\User::factory()->admin()->create())
        ->get('/admin/monitoring-backup')
        ->assertOk()
        ->assertSee('Monitoring Backup')
        ->assertSee($event->server)
        ->assertSee($event->nama_db)
        ->assertSee('1,250.50 MB')
        ->assertSee($event->lokasi_file);

    $response->assertSee('href="/admin/monitoring-backup"', false);

    $this->actingAs(\App\Models\User::factory()->create(['role' => 'officer']))
        ->get('/admin/monitoring-backup')
        ->assertForbidden();
});

it('filters backup events by keyword status and date range', function () {
    BackupMonitoringEvent::query()->create(backupMonitoringPayload([
        'tanggal' => '2026-10-30 21:30:00',
        'server' => 'DB-SERVER-01',
        'status' => 'SUCCESS',
    ]));
    BackupMonitoringEvent::query()->create(backupMonitoringPayload([
        'tanggal' => '2026-10-29 21:30:00',
        'server' => 'DB-SERVER-02',
        'status' => 'FAILED',
        'nama_db' => 'db_archive',
    ]));

    $this->actingAs(\App\Models\User::factory()->admin()->create())
        ->get('/admin/monitoring-backup?keyword=core&status=SUCCESS&date_from=2026-10-30&date_until=2026-10-30')
        ->assertOk()
        ->assertSee('DB-SERVER-01')
        ->assertDontSee('DB-SERVER-02')
        ->assertSee('Menampilkan 1 dari 2 event backup.');
});

it('validates backup monitoring date filters', function () {
    $this->actingAs(\App\Models\User::factory()->admin()->create())
        ->get('/admin/monitoring-backup?date_from=2026-10-31&date_until=2026-10-30')
        ->assertSessionHasErrors('date_until');
});

it('exports backup events matching the selected filters', function () {
    BackupMonitoringEvent::query()->create(backupMonitoringPayload([
        'tanggal' => '2026-10-30 21:30:00',
        'server' => 'DB-SERVER-01',
        'status' => 'SUCCESS',
    ]));
    BackupMonitoringEvent::query()->create(backupMonitoringPayload([
        'tanggal' => '2026-10-29 21:30:00',
        'server' => 'DB-SERVER-02',
        'status' => 'FAILED',
        'nama_db' => 'db_archive',
    ]));

    Excel::fake();
    $this->travelTo(Carbon::parse('2026-10-31 12:00:00'));

    $this->actingAs(\App\Models\User::factory()->admin()->create())
        ->get('/admin/monitoring-backup/export?keyword=core&status=SUCCESS&date_from=2026-10-30&date_until=2026-10-30')
        ->assertOk();

    Excel::assertDownloaded('monitoring-backup-20261031-120000.xlsx', function (BackupMonitoringEventsExport $export): bool {
        return $export->query()->get()->pluck('server')->all() === ['DB-SERVER-01'];
    });

    $this->travelBack();
});

it('restricts backup monitoring exports to admins', function () {
    $this->actingAs(\App\Models\User::factory()->create(['role' => 'officer']))
        ->get('/admin/monitoring-backup/export')
        ->assertForbidden();
});

it('generates an xlsx workbook for backup monitoring exports', function () {
    BackupMonitoringEvent::query()->create(backupMonitoringPayload());

    $workbook = Excel::raw(
        new BackupMonitoringEventsExport(BackupMonitoringEvent::query()),
        ExcelWriter::XLSX
    );

    expect(substr($workbook, 0, 2))->toBe('PK');
});
