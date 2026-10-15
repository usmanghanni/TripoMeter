<?php

use App\Models\Setting;
use App\Models\Trip;
use App\NativeComponents\Speedometer;
use App\Tasks\LookUpSpeedLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Geolocation\LocationUpdated;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('records a trip from start to stop', function () {
    $buffered = [
        ['latitude' => 0.0, 'longitude' => 0.0, 'speed' => 10.0, 'accuracy' => 5, 'timestamp' => 1_000_000],
        ['latitude' => 0.001, 'longitude' => 0.0, 'speed' => 10.0, 'accuracy' => 5, 'timestamp' => 1_011_000],
    ];

    $bridge = Native::fakeBridge()->respondTo('Geolocation.DrainWatchBuffer', function () use (&$buffered) {
        [$fixes, $buffered] = [$buffered, []];

        return ['fixes' => $fixes, 'cursor' => count($fixes), 'size' => count($fixes)];
    });

    $screen = Native::test(Speedometer::class)
        ->assertSee('Start trip')
        ->tap('Start trip')
        ->assertSee('Stop trip');

    $trip = Trip::sole();
    $bridge->assertCalled('Geolocation.StartBackgroundWatch', fn (array $params) => $params['id'] === $trip->watch_id);

    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 10.0, 'id' => $trip->watch_id])
        ->assertSet('speedMps', 10.0);

    expect($trip->points()->count())->toBe(2)
        ->and($trip->fresh()->distance_m)->toEqualWithDelta(111.2, 0.5);

    $screen->tap('Stop trip')->assertSee('Start trip');

    $bridge->assertCalled('Geolocation.StopBackgroundWatch');
    expect($trip->fresh()->ended_at)->not->toBeNull();
});

it('shows a permission hint when location is not granted', function () {
    Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');

    $screen->emitNative(LocationUpdated::class, ['success' => false, 'error' => 'permission_required', 'id' => Trip::sole()->watch_id])
        ->assertSee('Allow location access, then tap Start again.');
});

it('vibrates once each time the speed crosses the limit', function () {
    Setting::current()->update(['unit' => 'kmh', 'speed_limit_kmh' => 50]);
    $bridge = Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');
    $watchId = Trip::sole()->watch_id;
    $emitSpeed = fn (float $metersPerSecond) => $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => $metersPerSecond, 'id' => $watchId]);

    $emitSpeed(20.0); // 72 km/h
    $emitSpeed(21.0);
    $bridge->assertCalledTimes('Device.Vibrate', 1);

    $emitSpeed(10.0); // 36 km/h, back under
    $emitSpeed(20.0);
    $bridge->assertCalledTimes('Device.Vibrate', 2);
});

it('shows speed in the selected unit', function () {
    Setting::current()->update(['unit' => 'mph']);
    Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');

    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 10.0, 'id' => Trip::sole()->watch_id])
        ->assertSee('22')
        ->assertSee('mph');
});

it('shows the road limit in mph and alerts on the converted limit', function () {
    Setting::current()->update(['unit' => 'mph', 'speed_limit_kmh' => 0]);
    Http::fake([LookUpSpeedLimit::ENDPOINT => Http::response(['elements' => [['tags' => ['maxspeed' => '50']]]])]);
    AsyncTask::fake();
    $bridge = Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');

    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 15.0, 'latitude' => 0.0, 'longitude' => 0.0, 'id' => Trip::sole()->watch_id])
        ->assertSee('31') // 50 km/h road limit in mph
        ->assertSee('34'); // 15 m/s in mph, over the limit

    $bridge->assertCalledTimes('Device.Vibrate', 1);
    $bridge->assertCalled('SpeedTracker.SetUnit', fn (array $params) => $params['unit'] === 'mph');
});

it('shows the road limit, throttles lookups, and alerts above it', function () {
    Http::fake([LookUpSpeedLimit::ENDPOINT => Http::response(['elements' => [['tags' => ['maxspeed' => '50']]]])]);
    $tasks = AsyncTask::fake();
    $bridge = Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');
    $watchId = Trip::sole()->watch_id;

    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 10.0, 'latitude' => 0.0, 'longitude' => 0.0, 'id' => $watchId])
        ->assertSet('roadLimitKmh', 50)
        ->assertSee('Road limit');
    $bridge->assertNotCalled('Device.Vibrate');

    // 50 m further, 72 km/h: no new lookup yet, but over the road limit.
    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 20.0, 'latitude' => 0.00045, 'longitude' => 0.0, 'id' => $watchId]);
    $tasks->assertDispatchedTimes(1);
    $bridge->assertCalledTimes('Device.Vibrate', 1);
});

it('ignores the road limit when road alerts are off', function () {
    Setting::current()->update(['road_limit_alerts' => false]);
    Http::fake([LookUpSpeedLimit::ENDPOINT => Http::response(['elements' => [['tags' => ['maxspeed' => '50']]]])]);
    AsyncTask::fake();
    $bridge = Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');

    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 20.0, 'latitude' => 0.0, 'longitude' => 0.0, 'id' => Trip::sole()->watch_id]);
    $bridge->assertNotCalled('Device.Vibrate');
});

it('alerts with vibration, sound, or both per settings', function (string $mode, int $vibrations, int $sounds) {
    Setting::current()->update(['speed_limit_kmh' => 50, 'alert_mode' => $mode]);
    $bridge = Native::fakeBridge();

    $screen = Native::test(Speedometer::class)->tap('Start trip');
    $screen->emitNative(LocationUpdated::class, ['success' => true, 'speed' => 20.0, 'id' => Trip::sole()->watch_id]);

    $bridge->assertCalledTimes('Device.Vibrate', $vibrations);
    $bridge->assertCalledTimes('SpeedTracker.PlayAlert', $sounds);
})->with([
    'vibrate' => ['vibrate', 1, 0],
    'sound' => ['sound', 0, 1],
    'both' => ['both', 1, 1],
]);

it('keeps the screen awake while the app is open', function () {
    $bridge = Native::fakeBridge();

    Native::test(Speedometer::class);

    $bridge->assertCalled('SpeedTracker.KeepScreenOn', fn (array $params) => $params['enabled'] === true);
});
