<?php

use App\Models\Setting;
use App\Models\Trip;
use App\NativeComponents\Settings;
use App\NativeComponents\Speedometer;
use App\Support\NativeTrips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->nativeState = ['active' => false, 'finished' => []];
    $this->buffered = [];

    $this->bridge = Native::fakeBridge()
        ->respondTo('SpeedTracker.NativeTrips', fn () => $this->nativeState)
        ->respondTo('Geolocation.DrainWatchBuffer', function () {
            [$fixes, $this->buffered] = [$this->buffered, []];

            return ['fixes' => $fixes, 'cursor' => count($fixes), 'size' => count($fixes)];
        });

    // Two fixes 0.01° (~1.1 km) apart, tagged with a trip's watch id.
    $this->fixesFor = fn (string $watchId, int $startMs) => [
        ['latitude' => 0.0, 'longitude' => 0.0, 'speed' => 20.0, 'accuracy' => 5, 'timestamp' => $startMs, 'watch_id' => $watchId],
        ['latitude' => 0.01, 'longitude' => 0.0, 'speed' => 20.0, 'accuracy' => 5, 'timestamp' => $startMs + 60_000, 'watch_id' => $watchId],
    ];
});

it('saves trips started and stopped from the widget while the app was closed', function () {
    $start = now()->subHour()->getTimestampMs();
    $this->nativeState['finished'] = [
        ['id' => 'widget-1', 'started_at' => $start, 'ended_at' => $start + 120_000, 'source' => 'widget'],
        ['id' => 'widget-2', 'started_at' => $start + 600_000, 'ended_at' => $start + 700_000, 'source' => 'widget'],
    ];
    $this->buffered = [...($this->fixesFor)('widget-1', $start), ...($this->fixesFor)('widget-2', $start + 600_000)];

    expect(NativeTrips::sync())->toBeNull();

    $trips = Trip::orderBy('started_at')->get();

    expect($trips)->toHaveCount(2)
        ->and($trips->every(fn (Trip $trip) => $trip->ended_at !== null && $trip->points()->count() === 2))->toBeTrue()
        ->and($trips[0]->distance_m)->toEqualWithDelta(1112.0, 5.0)
        ->and($trips[0]->started_at->getTimestampMs())->toBe(intdiv($start, 1000) * 1000);

    $this->bridge->assertCalled('SpeedTracker.AcknowledgeNativeTrip', fn (array $params) => $params['id'] === 'widget-1');
    $this->bridge->assertCalled('SpeedTracker.AcknowledgeNativeTrip', fn (array $params) => $params['id'] === 'widget-2');
    $this->bridge->assertCalled('Geolocation.TrimWatchBuffer');
});

it('drops automatically detected trips that are too short', function () {
    $start = now()->subHour()->getTimestampMs();
    $this->nativeState['finished'] = [['id' => 'auto-1', 'started_at' => $start, 'ended_at' => $start + 60_000, 'source' => 'auto']];

    NativeTrips::sync();

    expect(Trip::count())->toBe(0);
    $this->bridge->assertCalled('SpeedTracker.AcknowledgeNativeTrip', fn (array $params) => $params['id'] === 'auto-1');
});

it('keeps automatically detected trips with a real distance', function () {
    $start = now()->subHour()->getTimestampMs();
    $this->nativeState['finished'] = [['id' => 'auto-1', 'started_at' => $start, 'ended_at' => $start + 60_000, 'source' => 'auto']];
    $this->buffered = ($this->fixesFor)('auto-1', $start);

    NativeTrips::sync();

    expect(Trip::sole()->title)->toBe('Auto-recorded trip');
});

it('shows a trip started natively as recording on the speed screen', function () {
    $this->nativeState['active'] = ['id' => 'auto-2', 'started_at' => now()->subMinutes(5)->getTimestampMs(), 'source' => 'auto'];
    $this->buffered = ($this->fixesFor)('auto-2', now()->subMinutes(5)->getTimestampMs());

    Native::test(Speedometer::class)->assertSee('Stop trip');

    $trip = Trip::sole();

    expect($trip->watch_id)->toBe('auto-2')
        ->and($trip->ended_at)->toBeNull()
        ->and($trip->points()->count())->toBe(2);

    $this->bridge->assertCalled('Geolocation.StartBackgroundWatch', fn (array $params) => $params['id'] === 'auto-2');
});

it('ends the trip on resume when it was stopped from the notification', function () {
    $screen = Native::test(Speedometer::class)->tap('Start trip')->assertSee('Stop trip');
    $trip = Trip::sole();

    $this->nativeState['finished'] = [[
        'id' => $trip->watch_id,
        'started_at' => $trip->started_at->getTimestampMs(),
        'ended_at' => now()->getTimestampMs(),
        'source' => 'app',
    ]];

    $screen->call('onResume')->assertSee('Start trip')->assertSet('tripId', null);

    expect($trip->fresh()->ended_at)->not->toBeNull();
});

it('falls back to the database where native trips are unsupported', function () {
    $this->bridge->withoutCapability('SpeedTracker.NativeTrips');
    $open = Trip::factory()->create(['watch_id' => 'legacy']);

    expect(NativeTrips::sync()?->id)->toBe($open->id);
});

it('turns automatic recording on from settings and explains missing permissions', function () {
    $this->bridge->respondTo('SpeedTracker.SetAutoRecord', ['status' => 'background_location_required']);

    Native::test(Settings::class)
        ->toggle("__syncProperty('autoRecord')", true)
        ->tap('Save');

    expect(Setting::current()->auto_record)->toBeTrue();
    $this->bridge->assertCalled('SpeedTracker.SetAutoRecord', fn (array $params) => $params['enabled'] === true && $params['request'] === true);
});

it('re-registers driving detection quietly when the app opens', function () {
    Setting::current()->update(['auto_record' => true]);

    Native::test(Speedometer::class);

    $this->bridge->assertCalled('SpeedTracker.SetAutoRecord', fn (array $params) => $params['enabled'] === true && $params['request'] === false);
});
