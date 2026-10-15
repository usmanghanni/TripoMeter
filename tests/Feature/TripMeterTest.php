<?php

use App\Support\TripMeter;

it('adds distance only while moving and ignores inaccurate fixes', function () {
    TripMeter::reset();

    TripMeter::record(0.0, 0.0, 5, 10.0);
    TripMeter::record(0.001, 0.0, 5, 10.0); // ~111 m moving
    TripMeter::record(0.0011, 0.0, 5, 0.1); // jitter while parked
    TripMeter::record(0.5, 0.0, 200, 30.0); // inaccurate, ignored entirely

    $state = TripMeter::state();

    expect($state['meters'])->toEqualWithDelta(111.2, 0.5)
        ->and($state['max_mps'])->toBe(10.0)
        ->and($state['last'])->toBe(['latitude' => 0.0011, 'longitude' => 0.0]);

    TripMeter::reset();
    expect(TripMeter::state()['meters'])->toBe(0.0);
});
