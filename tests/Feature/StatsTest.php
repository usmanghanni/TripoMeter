<?php

use App\Models\Trip;
use App\NativeComponents\Stats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

    Trip::factory()->create(['started_at' => now(), 'distance_m' => 10_000]);
    Trip::factory()->create(['started_at' => now()->subHour(), 'distance_m' => 5_000]);
    Trip::factory()->create(['started_at' => now()->subDays(3), 'distance_m' => 20_000]);
    Trip::factory()->create(['started_at' => now()->subMonths(2), 'distance_m' => 100_000]);
    Trip::factory()->create(['started_at' => now()->subYears(2), 'distance_m' => 999_000]); // out of range
});

it('buckets distance per day including empty days', function () {
    $days = Trip::distanceByPeriod('daily');

    expect($days)->toHaveCount(14)
        ->and(end($days))->toBe(['label' => '15', 'meters' => 15_000.0])
        ->and($days[10])->toBe(['label' => '12', 'meters' => 20_000.0])
        ->and($days[0]['meters'])->toBe(0.0);
});

it('buckets distance per month', function () {
    $months = Trip::distanceByPeriod('monthly');

    expect($months)->toHaveCount(12)
        ->and(end($months))->toBe(['label' => 'Oct', 'meters' => 35_000.0])
        ->and($months[9])->toBe(['label' => 'Aug', 'meters' => 100_000.0]);
});

it('switches between daily and monthly charts', function () {
    Native::test(Stats::class)
        ->assertSee('Last 14 days')
        ->assertSee('35.0 km')
        ->set('periodIndex', 1)
        ->assertSee('Last 12 months')
        ->assertSee('135.0 km')
        ->assertSee('Best month');
});
