<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Facades\Cache;

/**
 * A resettable "trip B" meter: distance and top speed from every GPS fix the
 * Speed screen sees, whether or not a trip is being recorded.
 *
 * ponytail: only counts while the Speed screen is listening; switching tabs or
 * backgrounding pauses it (recorded trips use the native buffer instead).
 */
class TripMeter
{
    private const CACHE_KEY = 'trip_meter';

    /**
     * @return array{meters: float, max_mps: float, since: int, last: array{latitude: float, longitude: float}|null}
     */
    public static function state(): array
    {
        return Cache::get(self::CACHE_KEY) ?? self::fresh();
    }

    /**
     * Add one fix to the meter and return the new state.
     *
     * @return array{meters: float, max_mps: float, since: int, last: array{latitude: float, longitude: float}|null}
     */
    public static function record(float $latitude, float $longitude, ?float $accuracy = null, ?float $speed = null): array
    {
        $state = self::state();

        if ($accuracy !== null && $accuracy > Trip::MAX_ACCURACY_M) {
            return $state;
        }

        if ($state['last'] !== null) {
            $step = Trip::distanceBetween($state['last']['latitude'], $state['last']['longitude'], $latitude, $longitude);
            if (Trip::isMoving($speed, $step, $accuracy)) {
                $state['meters'] += $step;
            }
        }

        $state['max_mps'] = max($state['max_mps'], Trip::displaySpeed($speed, $accuracy));

        $state['last'] = ['latitude' => $latitude, 'longitude' => $longitude];

        Cache::forever(self::CACHE_KEY, $state);

        return $state;
    }

    public static function reset(): void
    {
        Cache::forever(self::CACHE_KEY, self::fresh());
    }

    /**
     * @return array{meters: float, max_mps: float, since: int, last: null}
     */
    private static function fresh(): array
    {
        return ['meters' => 0.0, 'max_mps' => 0.0, 'since' => time(), 'last' => null];
    }
}
