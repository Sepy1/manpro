<?php

use App\Models\VpnClient;
use App\Models\VpnGroup;
use Illuminate\Support\Carbon;

it('allows access at the configured days and hours', function () {
    $client = new VpnClient(['access_schedule' => [
        'days' => [1, 3, 5],
        'start' => '08:00',
        'end' => '17:00',
    ]]);

    expect($client->isWithinAccessSchedule(Carbon::parse('2026-11-23 08:00', 'Asia/Jakarta')))->toBeTrue()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-23 16:59', 'Asia/Jakarta')))->toBeTrue()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-23 17:00', 'Asia/Jakarta')))->toBeFalse()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-24 09:00', 'Asia/Jakarta')))->toBeFalse();
});

it('supports schedules that cross midnight', function () {
    $client = new VpnClient(['access_schedule' => [
        'days' => [5],
        'start' => '22:00',
        'end' => '06:00',
    ]]);

    expect($client->isWithinAccessSchedule(Carbon::parse('2026-11-27 23:00', 'Asia/Jakarta')))->toBeTrue()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-28 05:59', 'Asia/Jakarta')))->toBeTrue()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-28 06:00', 'Asia/Jakarta')))->toBeFalse();
});

it('allows access all day when no schedule is configured', function () {
    expect((new VpnClient)->isWithinAccessSchedule(Carbon::parse('2026-11-22 03:00', 'Asia/Jakarta')))->toBeTrue();
});

it('inherits the group schedule unless the client has its own schedule', function () {
    $group = new VpnGroup(['access_schedule' => [
        'days' => [1], 'start' => '08:00', 'end' => '17:00',
    ]]);
    $client = (new VpnClient)->setRelation('group', $group);

    expect($client->isWithinAccessSchedule(Carbon::parse('2026-11-23 09:00', 'Asia/Jakarta')))->toBeTrue()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-24 09:00', 'Asia/Jakarta')))->toBeFalse();

    $client->access_schedule = ['days' => [2], 'start' => '09:00', 'end' => '10:00'];

    expect($client->isWithinAccessSchedule(Carbon::parse('2026-11-23 09:30', 'Asia/Jakarta')))->toBeFalse()
        ->and($client->isWithinAccessSchedule(Carbon::parse('2026-11-24 09:30', 'Asia/Jakarta')))->toBeTrue();
});
