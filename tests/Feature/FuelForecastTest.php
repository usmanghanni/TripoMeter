<?php

use App\Models\FuelFill;
use App\Models\Setting;
use App\Models\Trip;
use App\NativeComponents\FuelLog;
use App\NativeComponents\Speedometer;
use App\Support\FuelForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
    $this->travelTo('2026-10-18 12:00:00');

    // 20 km/L, about 400 km between full fills; last full tank on Oct 10.
    FuelFill::factory()->create(['filled_on' => '2026-09-20', 'odometer_km' => 1000, 'liters' => 20]);
    FuelFill::factory()->create(['filled_on' => '2026-10-10', 'odometer_km' => 1400, 'liters' => 20]);
});

function driveSinceLastFill(float $kilometers): void
{
    Trip::factory()->create([
        'started_at' => '2026-10-12 09:00:00',
        'ended_at' => '2026-10-12 12:00:00',
        'distance_m' => $kilometers * 1000,
    ]);
}

it('needs fuel history before estimating', function () {
    FuelFill::query()->delete();

    expect(FuelForecast::current())->toBeNull();
});

it('estimates range from the distance between full fills', function () {
    driveSinceLastFill(100);

    expect(FuelForecast::current())
        ->full_range_km->toEqualWithDelta(400.0, 0.01)
        ->remaining_km->toEqualWithDelta(300.0, 0.01)
        ->is_low->toBeFalse();
});

it('uses the tank size when known and counts top-ups', function () {
    Setting::current()->update(['tank_capacity_l' => 35]);
    FuelFill::factory()->create(['filled_on' => '2026-10-13', 'odometer_km' => 1600, 'liters' => 5, 'is_full_tank' => false]);
    driveSinceLastFill(500);

    // 35 L x 20 km/L = 700 km, +100 km top-up capped at a full tank, minus 500 driven.
    expect(FuelForecast::current())
        ->full_range_km->toEqualWithDelta(700.0, 0.01)
        ->remaining_km->toEqualWithDelta(200.0, 0.01)
        ->is_low->toBeFalse();
});

it('reminds once per tank when fuel runs low', function () {
    driveSinceLastFill(360);

    expect(FuelForecast::remindIfLow())->toBeTrue()
        ->and(FuelForecast::remindIfLow())->toBeFalse();

    $this->bridge->assertCalled('SpeedTracker.Notify', fn (array $params) => $params['title'] === 'Time to refuel' && str_contains($params['body'], '40 km'));
});

it('does not remind when reminders are off', function () {
    Setting::current()->update(['refuel_reminders' => false]);
    driveSinceLastFill(390);

    expect(FuelForecast::remindIfLow())->toBeFalse();
});

it('shows a refuel banner on the speed screen and the range on the fuel screen', function () {
    driveSinceLastFill(380);

    Native::test(Speedometer::class)->assertSee('Time to refuel')->assertSee('About 20 km left');
    Native::test(FuelLog::class)->assertSee('~20 km')->assertSee('Refuel soon');
});
