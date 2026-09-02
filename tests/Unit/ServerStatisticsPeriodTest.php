<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ServerStatisticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class ServerStatisticsPeriodTest extends TestCase
{
    public function test_period_uses_selected_hours(): void
    {
        [$start, $end, $error] = $this->resolvePeriod([
            'start_date' => '2026-09-02',
            'start_time' => '08:15',
            'end_date' => '2026-09-02',
            'end_time' => '11:45',
        ]);

        $this->assertNull($error);
        $this->assertSame('2026-09-02 08:15:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 11:45:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_period_keeps_full_day_defaults_for_legacy_requests(): void
    {
        [$start, $end, $error] = $this->resolvePeriod([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ]);

        $this->assertNull($error);
        $this->assertSame('2026-09-01 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_period_rejects_reversed_hours_on_the_same_date(): void
    {
        [, , $error] = $this->resolvePeriod([
            'start_date' => '2026-09-02',
            'start_time' => '17:00',
            'end_date' => '2026-09-02',
            'end_time' => '08:00',
        ]);

        $this->assertSame('Waktu mulai tidak boleh lebih besar dari waktu akhir.', $error);
    }

    public function test_period_rejects_invalid_hour_values(): void
    {
        [, , $error] = $this->resolvePeriod([
            'start_date' => '2026-09-02',
            'start_time' => '25:00',
            'end_date' => '2026-09-02',
            'end_time' => '26:00',
        ]);

        $this->assertSame('Format periode tidak valid.', $error);
    }

    public function test_selected_hours_are_forwarded_to_prtg(): void
    {
        config()->set('services.prtg', [
            'base_url' => 'https://prtg.test',
            'username' => 'tester',
            'passhash' => 'secret',
            'verify_ssl' => false,
        ]);

        Http::fake([
            'https://prtg.test/api/historicdata.json*' => Http::response(['histdata' => []]),
        ]);

        [$start, $end] = $this->resolvePeriod([
            'start_date' => '2026-09-02',
            'start_time' => '08:15',
            'end_date' => '2026-09-02',
            'end_time' => '11:45',
        ]);

        $method = new ReflectionMethod(ServerStatisticsController::class, 'fetchHistoricDataByObjid');
        $method->invoke(new ServerStatisticsController, '1234', $start, $end);

        Http::assertSent(fn ($request) => $request['sdate'] === '2026-09-02-08-15-00'
            && $request['edate'] === '2026-09-02-11-45-59'
            && (int) $request['avg'] === 60);
    }

    public function test_long_ranges_keep_hourly_prtg_aggregation(): void
    {
        config()->set('services.prtg', [
            'base_url' => 'https://prtg.test',
            'username' => 'tester',
            'passhash' => 'secret',
            'verify_ssl' => false,
        ]);

        Http::fake([
            'https://prtg.test/api/historicdata.json*' => Http::response(['histdata' => []]),
        ]);

        $method = new ReflectionMethod(ServerStatisticsController::class, 'fetchHistoricDataByObjid');
        $method->invoke(
            new ServerStatisticsController,
            '1234',
            \Illuminate\Support\Carbon::parse('2026-09-01 00:00:00'),
            \Illuminate\Support\Carbon::parse('2026-09-03 23:59:59')
        );

        Http::assertSent(fn ($request) => (int) $request['avg'] === 3600);
    }

    private function resolvePeriod(array $query): array
    {
        $method = new ReflectionMethod(ServerStatisticsController::class, 'resolvePeriodFromRequest');

        return $method->invoke(new ServerStatisticsController, Request::create('/', 'GET', $query));
    }
}
