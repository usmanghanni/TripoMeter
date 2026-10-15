<?php

namespace App\Tasks;

use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Throwable;

/**
 * Looks up the posted speed limit (km/h) of the road nearest a coordinate using
 * OpenStreetMap's free Overpass API. Runs on a background PHP thread.
 */
class LookUpSpeedLimit extends AsyncTask
{
    public const ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /**
     * Search radius in meters around the fix.
     *
     * ponytail: takes the first tagged road in range, can pick a cross street at junctions;
     * rank by distance to the fix and heading if that bites.
     */
    public const RADIUS_M = 20;

    public function handle(float $latitude, float $longitude): ?int
    {
        $query = sprintf(
            '[out:json][timeout:8];way(around:%d,%F,%F)[highway][maxspeed];out tags 1;',
            self::RADIUS_M,
            $latitude,
            $longitude,
        );

        try {
            $response = Http::timeout(10)
                ->withUserAgent('SpeedTripRecorder/1.0')
                ->asForm()
                ->post(self::ENDPOINT, ['data' => $query]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return self::parseMaxSpeed($response->json('elements.0.tags.maxspeed'));
    }

    /**
     * Convert an OSM maxspeed tag ("50", "30 mph", "none", "DE:urban") to km/h.
     */
    public static function parseMaxSpeed(?string $maxSpeed): ?int
    {
        if ($maxSpeed === null || ! preg_match('/^\s*(\d+(?:\.\d+)?)\s*(mph|km\/h|kmh)?\s*$/i', $maxSpeed, $matches)) {
            return null;
        }

        $value = (float) $matches[1];

        return (int) round(strtolower($matches[2] ?? '') === 'mph' ? $value * 1.609344 : $value);
    }
}
