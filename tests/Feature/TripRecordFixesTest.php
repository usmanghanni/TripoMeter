<?php

use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accumulates distance and max speed from fixes', function () {
    $trip = Trip::factory()->create();

    // ~111 m apart (0.001° latitude), 10 s apart, second fix has no GPS speed.
    $trip->recordFixes([
        ['latitude' => 0.0, 'longitude' => 0.0, 'speed' => 5.0, 'accuracy' => 5, 'timestamp' => 1_000_000],
        ['latitude' => 0.001, 'longitude' => 0.0, 'speed' => null, 'accuracy' => 5, 'timestamp' => 1_010_000],
    ]);

    expect($trip->fresh()->distance_m)->toEqualWithDelta(111.2, 0.5)
        ->and($trip->fresh()->max_speed_mps)->toEqualWithDelta(11.12, 0.05)
        ->and($trip->points()->count())->toBe(2);
});

it('continues distance from the last stored point across batches', function () {
    $trip = Trip::factory()->create();

    $trip->recordFixes([['latitude' => 0.0, 'longitude' => 0.0, 'speed' => 1.0, 'timestamp' => 1_000_000]]);
    $trip->recordFixes([['latitude' => 0.001, 'longitude' => 0.0, 'speed' => 1.0, 'timestamp' => 1_010_000]]);

    expect($trip->fresh()->distance_m)->toEqualWithDelta(111.2, 0.5);
});

it('skips inaccurate fixes and diagnostic markers', function () {
    $trip = Trip::factory()->create();

    $trip->recordFixes([
        ['latitude' => 0.0, 'longitude' => 0.0, 'accuracy' => Trip::MAX_ACCURACY_M + 1, 'speed' => 50.0],
        ['event' => 'diagnostic'],
    ]);

    expect($trip->points()->count())->toBe(0)
        ->and($trip->fresh()->max_speed_mps)->toBe(0.0);
});
