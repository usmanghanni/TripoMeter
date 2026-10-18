<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Trip;
use Illuminate\Support\Carbon;

/**
 * Turns a trip's recorded points into what the trip screen draws: a route
 * plot coloured by speed, a speed-over-time chart and a segment timeline.
 *
 * Bands are relative to the trip's own top speed (so a city trip still shows
 * where it was quick), except "over", which is the personal speed limit.
 */
class TripTimeline
{
    /**
     * Route plot width in points; the height follows the route's shape.
     */
    public const ROUTE_WIDTH = 300;

    public const ROUTE_MAX_HEIGHT = 260;

    public const ROUTE_DOT_SIZE = 6;

    public const MAX_ROUTE_DOTS = 160;

    public const CHART_HEIGHT = 110;

    public const MAX_CHART_BARS = 36;

    public const MAX_SEGMENTS = 30;

    /**
     * Fraction of the trip's top speed where a band starts.
     */
    private const MEDIUM_RATIO = 0.4;

    private const FAST_RATIO = 0.75;

    /**
     * Fill classes per band, shared by the plot, chart and legend.
     *
     * @var array<string, string>
     */
    public const BAND_COLORS = [
        'stopped' => 'bg-zinc-400',
        'slow' => 'bg-sky-500',
        'medium' => 'bg-green-500',
        'fast' => 'bg-amber-500',
        'over' => 'bg-red-600',
    ];

    /**
     * @var array<string, string>
     */
    public const BAND_LABELS = [
        'stopped' => 'Stopped',
        'slow' => 'Slow',
        'medium' => 'Medium',
        'fast' => 'Fast',
        'over' => 'Over limit',
    ];

    /**
     * @param  list<array{latitude: float, longitude: float, speed: float, at: Carbon}>  $points
     */
    private function __construct(
        public readonly Trip $trip,
        private readonly Setting $setting,
        private readonly array $points,
    ) {}

    public static function for(Trip $trip, Setting $setting): self
    {
        return new self($trip, $setting, $trip->points()
            ->toBase()
            ->get(['latitude', 'longitude', 'speed_mps', 'recorded_at'])
            ->map(fn (object $point): array => [
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'speed' => (float) ($point->speed_mps ?? 0),
                'at' => Carbon::parse($point->recorded_at),
            ])
            ->all());
    }

    public function hasRoute(): bool
    {
        return count($this->points) >= 2;
    }

    /**
     * The band a speed (m/s) falls in for this trip.
     */
    public function band(float $speed): string
    {
        if ($speed < Trip::MIN_MOVING_SPEED_MPS) {
            return 'stopped';
        }

        if ($this->setting->isOverLimit($speed)) {
            return 'over';
        }

        $ratio = $this->trip->max_speed_mps > 0 ? $speed / $this->trip->max_speed_mps : 0;

        return match (true) {
            $ratio >= self::FAST_RATIO => 'fast',
            $ratio >= self::MEDIUM_RATIO => 'medium',
            default => 'slow',
        };
    }

    /**
     * Legend entries with the speed range each band covers, in the display unit.
     *
     * @return list<array{band: string, label: string, range: string, color: string}>
     */
    public function legend(): array
    {
        $max = $this->setting->speed($this->trip->max_speed_mps);
        $medium = (int) round($max * self::MEDIUM_RATIO);
        $fast = (int) round($max * self::FAST_RATIO);
        $unit = $this->setting->speedUnit();

        $ranges = [
            'stopped' => 'not moving',
            'slow' => "under {$medium} {$unit}",
            'medium' => "{$medium}–{$fast} {$unit}",
            'fast' => "{$fast}+ {$unit}",
        ];

        if ($this->setting->speed_limit_kmh > 0) {
            $ranges['over'] = 'above '.$this->setting->fromKmh($this->setting->speed_limit_kmh)." {$unit}";
        }

        return collect($ranges)
            ->map(fn (string $range, string $band): array => [
                'band' => $band,
                'label' => self::BAND_LABELS[$band],
                'range' => $range,
                'color' => self::BAND_COLORS[$band],
            ])
            ->values()
            ->all();
    }

    /**
     * Downsampled route as dots positioned inside a ROUTE_WIDTH-wide box
     * (equirectangular projection, north up, aspect ratio kept).
     *
     * @return array{height: int, dots: list<array{x: int, y: int, size: int, color: string}>}
     */
    public function route(): array
    {
        if (! $this->hasRoute()) {
            return ['height' => 0, 'dots' => []];
        }

        $latitudes = array_column($this->points, 'latitude');
        $longitudes = array_column($this->points, 'longitude');
        [$minLat, $maxLat, $minLng, $maxLng] = [min($latitudes), max($latitudes), min($longitudes), max($longitudes)];

        $lngScale = cos(deg2rad(($minLat + $maxLat) / 2));
        $spanX = max(($maxLng - $minLng) * $lngScale, 1e-9);
        $spanY = max($maxLat - $minLat, 1e-9);

        $inner = self::ROUTE_WIDTH - self::ROUTE_DOT_SIZE;
        $scale = min($inner / $spanX, (self::ROUTE_MAX_HEIGHT - self::ROUTE_DOT_SIZE) / $spanY);
        $offsetX = ($inner - $spanX * $scale) / 2;
        $height = (int) ceil($spanY * $scale) + self::ROUTE_DOT_SIZE;

        $sampled = $this->sample($this->points, self::MAX_ROUTE_DOTS);
        $lastIndex = count($sampled) - 1;

        $dots = [];

        foreach ($sampled as $index => $point) {
            $isEnd = $index === 0 || $index === $lastIndex;
            $size = $isEnd ? self::ROUTE_DOT_SIZE * 2 : self::ROUTE_DOT_SIZE;
            $centering = intdiv($size - self::ROUTE_DOT_SIZE, 2);

            $dots[] = [
                'x' => max(0, (int) round($offsetX + ($point['longitude'] - $minLng) * $lngScale * $scale) - $centering),
                'y' => max(0, (int) round(($maxLat - $point['latitude']) * $scale) - $centering),
                'size' => $size,
                'color' => match (true) {
                    $index === 0 => 'bg-theme-primary',
                    $index === $lastIndex => 'bg-theme-on-surface',
                    default => self::BAND_COLORS[$this->band($point['speed'])],
                },
            ];
        }

        // Start and end on top.
        usort($dots, fn (array $a, array $b): int => $a['size'] <=> $b['size']);

        return ['height' => $height, 'dots' => $dots];
    }

    /**
     * Average speed over equal slices of the trip's time, for a bar chart.
     *
     * @return list<array{label: string, height: int, color: string, a11y: string}>
     */
    public function speedBars(): array
    {
        if (! $this->hasRoute()) {
            return [];
        }

        $slices = $this->slices(self::MAX_CHART_BARS);
        $maxSpeed = max(Trip::MIN_MOVING_SPEED_MPS, ...array_column($slices, 'average'));
        $unit = $this->setting->speedUnit();

        return array_map(fn (array $slice): array => [
            'label' => $slice['from']->format('H:i'),
            'height' => $slice['average'] > 0 ? max(2, (int) round($slice['average'] / $maxSpeed * self::CHART_HEIGHT)) : 0,
            'color' => self::BAND_COLORS[$this->band($slice['average'])],
            'a11y' => $slice['from']->format('H:i').': '.number_format($this->setting->speed($slice['average'])).' '.$unit,
        ], $slices);
    }

    /**
     * The trip split into consecutive stretches of whole minutes (at most
     * MAX_SEGMENTS), each with where it started for opening in a map.
     *
     * @return list<array{from: string, to: string, distance: float, average: float, max: float, band: string, color: string, latitude: float, longitude: float}>
     */
    public function segments(): array
    {
        if (! $this->hasRoute()) {
            return [];
        }

        return array_map(fn (array $slice): array => [
            'from' => $slice['from']->format('H:i'),
            'to' => $slice['to']->format('H:i'),
            'distance' => $this->setting->distance($slice['meters']),
            'average' => $this->setting->speed($slice['average']),
            'max' => $this->setting->speed($slice['max']),
            'band' => $this->band($slice['average']),
            'color' => self::BAND_COLORS[$this->band($slice['average'])],
            'latitude' => $slice['latitude'],
            'longitude' => $slice['longitude'],
        ], $this->slices(self::MAX_SEGMENTS, wholeMinutes: true));
    }

    /**
     * Seconds spent in each band, keyed by band.
     *
     * @return array<string, int>
     */
    public function secondsByBand(): array
    {
        $seconds = array_fill_keys(array_keys(self::BAND_COLORS), 0);

        foreach ($this->pairs() as [$previous, $point]) {
            $seconds[$this->band($point['speed'])] += (int) round($previous['at']->diffInMilliseconds($point['at']) / 1000);
        }

        return $seconds;
    }

    /**
     * "45s", "12m" or "1h 05m".
     */
    public static function shortDuration(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => "{$seconds}s",
            $seconds < 3600 => intdiv($seconds, 60).'m',
            default => sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)),
        };
    }

    /**
     * A Google Maps directions link through up to 8 evenly spaced route points.
     */
    public function mapsUrl(): ?string
    {
        if (! $this->hasRoute()) {
            return null;
        }

        $coordinates = fn (array $point): string => round($point['latitude'], 6).','.round($point['longitude'], 6);
        $waypoints = array_slice($this->sample($this->points, 10), 1, -1);

        return 'https://www.google.com/maps/dir/?'.http_build_query([
            'api' => 1,
            'origin' => $coordinates($this->points[0]),
            'destination' => $coordinates($this->points[array_key_last($this->points)]),
            'waypoints' => implode('|', array_map($coordinates, $waypoints)),
            'travelmode' => 'driving',
        ]);
    }

    /**
     * Split the trip's time into at most $count equal slices.
     *
     * @return list<array{from: Carbon, to: Carbon, meters: float, average: float, max: float, latitude: float, longitude: float}>
     */
    private function slices(int $count, bool $wholeMinutes = false): array
    {
        $start = $this->points[0]['at'];
        $totalSeconds = max(1, $start->diffInSeconds($this->points[array_key_last($this->points)]['at']));
        $sliceSeconds = $totalSeconds / $count;

        if ($wholeMinutes) {
            $sliceSeconds = max(1, (int) ceil($sliceSeconds / 60)) * 60;
        }

        $slices = [];

        foreach ($this->pairs() as [$previous, $point]) {
            $index = min((int) floor($start->diffInSeconds($previous['at']) / $sliceSeconds), $count - 1);

            $slices[$index] ??= [
                'from' => $previous['at'],
                'to' => $point['at'],
                'meters' => 0.0,
                'seconds' => 0.0,
                'max' => 0.0,
                'latitude' => $previous['latitude'],
                'longitude' => $previous['longitude'],
            ];

            $slices[$index]['to'] = $point['at'];
            $slices[$index]['seconds'] += $previous['at']->diffInMilliseconds($point['at']) / 1000;
            $slices[$index]['max'] = max($slices[$index]['max'], $point['speed']);

            if ($point['speed'] >= Trip::MIN_MOVING_SPEED_MPS) {
                $slices[$index]['meters'] += Trip::distanceBetween($previous['latitude'], $previous['longitude'], $point['latitude'], $point['longitude']);
            }
        }

        ksort($slices);

        return array_values(array_map(function (array $slice): array {
            $slice['average'] = $slice['seconds'] > 0 ? $slice['meters'] / $slice['seconds'] : 0.0;
            unset($slice['seconds']);

            return $slice;
        }, $slices));
    }

    /**
     * Consecutive point pairs.
     *
     * @return \Generator<int, array{0: array{latitude: float, longitude: float, speed: float, at: Carbon}, 1: array{latitude: float, longitude: float, speed: float, at: Carbon}}>
     */
    private function pairs(): \Generator
    {
        for ($index = 1; $index < count($this->points); $index++) {
            yield [$this->points[$index - 1], $this->points[$index]];
        }
    }

    /**
     * Up to $limit evenly spaced items, always keeping the first and last.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function sample(array $items, int $limit): array
    {
        $count = count($items);

        if ($count <= $limit) {
            return $items;
        }

        return array_map(
            fn (int $step): mixed => $items[(int) round($step * ($count - 1) / ($limit - 1))],
            range(0, $limit - 1),
        );
    }
}
