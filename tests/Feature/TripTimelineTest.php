<?php

use App\Models\Setting;
use App\Models\Trip;
use App\Support\TripTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    Native::fakeBridge();

    // Ten minutes heading north: 2 slow minutes, 6 fast, 2 stopped.
    $this->trip = Trip::factory()->create(['started_at' => '2026-10-01 08:00:00']);
    $start = $this->trip->started_at->getTimestampMs();
    $latitude = 0.0;
    $fixes = [];

    foreach (range(0, 600, 10) as $second) {
        $speed = match (true) {
            $second <= 120 => 5.0,
            $second <= 480 => 25.0,
            default => 0.0,
        };
        $latitude += $speed * 10 / 111_195;
        $fixes[] = ['latitude' => $latitude, 'longitude' => 0.0, 'speed' => $speed, 'accuracy' => 5, 'timestamp' => $start + $second * 1000];
    }

    $this->trip->recordFixes($fixes);
    $this->trip->update(['ended_at' => '2026-10-01 08:10:00']);
    $this->trip->refresh();
});

it('splits the trip into timed segments with speeds', function () {
    $segments = TripTimeline::for($this->trip, Setting::current())->segments();

    expect($segments)->toHaveCount(10)
        ->and($segments[0]['from'])->toBe('08:00')
        ->and($segments[0]['band'])->toBe('slow')
        ->and($segments[4]['average'])->toEqualWithDelta(90.0, 1.0) // 25 m/s in km/h
        ->and($segments[4]['band'])->toBe('fast')
        ->and($segments[9]['band'])->toBe('stopped');
});

it('totals time spent in each speed band', function () {
    $seconds = TripTimeline::for($this->trip, Setting::current())->secondsByBand();

    expect($seconds['slow'])->toBe(120)
        ->and($seconds['fast'])->toBe(360)
        ->and($seconds['stopped'])->toBe(120);
});

it('marks stretches over the personal limit', function () {
    Setting::current()->update(['speed_limit_kmh' => 80]);
    $timeline = TripTimeline::for($this->trip, Setting::current());

    expect($timeline->band(25.0))->toBe('over')
        ->and(collect($timeline->legend())->pluck('band')->all())->toContain('over');
});

it('plots the route inside the box, north up', function () {
    $route = TripTimeline::for($this->trip, Setting::current())->route();
    $dots = collect($route['dots']);

    expect($route['height'])->toBeLessThanOrEqual(TripTimeline::ROUTE_MAX_HEIGHT)
        ->and($dots->max('x'))->toBeLessThanOrEqual(TripTimeline::ROUTE_WIDTH)
        ->and($dots->firstWhere('color', 'bg-theme-primary')['y']) // start, south
        ->toBeGreaterThan($dots->firstWhere('color', 'bg-theme-on-surface')['y']); // end, north
});

it('links the route to Google Maps', function () {
    expect(TripTimeline::for($this->trip, Setting::current())->mapsUrl())
        ->toStartWith('https://www.google.com/maps/dir/?api=1&origin=')
        ->toContain('travelmode=driving');
});

it('shows the route and timeline on the trip screen', function () {
    Native::visit("/trips/{$this->trip->id}")
        ->assertSee('Route by speed')
        ->assertSee('Speed over time')
        ->assertSee('Time at each speed')
        ->assertSee('08:04 – 08:05')
        ->call('openSegment', 4)
        ->call('openRouteInMaps');
});

it('hides the timeline for trips without a route', function () {
    $empty = Trip::factory()->create(['started_at' => now()->subHour(), 'ended_at' => now()]);

    Native::visit("/trips/{$empty->id}")
        ->assertSee('Distance')
        ->assertDontSee('Route by speed');
});

it('formats short durations', function (int $seconds, string $expected) {
    expect(TripTimeline::shortDuration($seconds))->toBe($expected);
})->with([
    [0, '0s'],
    [45, '45s'],
    [720, '12m'],
    [3900, '1h 05m'],
]);
