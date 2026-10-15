<?php

namespace App\Models;

use Carbon\CarbonInterval;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    /**
     * Fixes less accurate than this (meters) are discarded.
     *
     * ponytail: fixed threshold, tune on a real device or make it a user setting if needed.
     */
    public const MAX_ACCURACY_M = 30;

    /**
     * GPS speed (m/s) below which the phone counts as standing still. Phones
     * report a few km/h of noise while parked, especially indoors.
     *
     * ponytail: fixed 3.6 km/h, tune on a real device (or per vehicle) if it
     * clips real crawling traffic or still lets jitter through.
     */
    public const MIN_MOVING_SPEED_MPS = 1.0;

    /**
     * Without a GPS speed, a position step must beat the fix accuracy (and at
     * least this many meters) to count as movement rather than jitter.
     */
    public const MIN_MOVING_STEP_M = 5;

    /**
     * Earth's mean radius in meters, used by the haversine formula.
     */
    private const EARTH_RADIUS_M = 6371000;

    /**
     * @var list<string>
     */
    protected $fillable = ['started_at', 'ended_at', 'distance_m', 'max_speed_mps', 'watch_id', 'title', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'distance_m' => 'float',
            'max_speed_mps' => 'float',
        ];
    }

    /**
     * @return HasMany<TripPoint, $this>
     */
    public function points(): HasMany
    {
        return $this->hasMany(TripPoint::class)->orderBy('recorded_at');
    }

    /**
     * Persist raw geolocation fixes, accumulating distance and max speed.
     *
     * @param  array<int, array{latitude?: float, longitude?: float, speed?: float|null, accuracy?: float|null, timestamp?: int}>  $fixes
     */
    public function recordFixes(array $fixes): void
    {
        $previous = $this->points()->reorder('recorded_at', 'desc')->first();

        foreach ($fixes as $fix) {
            if (! isset($fix['latitude'], $fix['longitude'])) {
                continue;
            }

            if (($fix['accuracy'] ?? 0) > self::MAX_ACCURACY_M) {
                continue;
            }

            $recordedAt = isset($fix['timestamp'])
                ? Carbon::createFromTimestampMs($fix['timestamp'])
                : now();

            $speed = isset($fix['speed']) && $fix['speed'] >= 0 ? (float) $fix['speed'] : null;

            if ($previous) {
                $step = self::distanceBetween($previous->latitude, $previous->longitude, $fix['latitude'], $fix['longitude']);
                $seconds = $previous->recorded_at->diffInMilliseconds($recordedAt) / 1000;

                if (self::isMoving($speed, $step, $fix['accuracy'] ?? null)) {
                    $this->distance_m += $step;
                    $speed ??= $seconds > 0 ? $step / $seconds : null;
                } else {
                    $speed = 0.0;
                }
            }

            if ($speed !== null && $speed >= self::MIN_MOVING_SPEED_MPS) {
                $this->max_speed_mps = max($this->max_speed_mps, $speed);
            }

            $previous = $this->points()->create([
                'latitude' => $fix['latitude'],
                'longitude' => $fix['longitude'],
                'speed_mps' => $speed,
                'accuracy' => $fix['accuracy'] ?? null,
                'recorded_at' => $recordedAt,
            ]);
        }

        $this->save();
    }

    /**
     * Distance driven per day (last 14 days) or per month (last 12 months), oldest first,
     * with empty periods included.
     *
     * @param  'daily'|'monthly'  $period
     * @return list<array{label: string, meters: float}>
     */
    public static function distanceByPeriod(string $period): array
    {
        $isMonthly = $period === 'monthly';
        $periodCount = $isMonthly ? 12 : 14;
        $start = $isMonthly ? now()->startOfMonth()->subMonths($periodCount - 1) : today()->subDays($periodCount - 1);
        $keyFormat = $isMonthly ? 'Y-m' : 'Y-m-d';

        $metersByKey = static::where('started_at', '>=', $start)
            ->get(['started_at', 'distance_m'])
            ->groupBy(fn (Trip $trip): string => $trip->started_at->format($keyFormat))
            ->map(fn ($trips): float => (float) $trips->sum('distance_m'));

        return collect(range(0, $periodCount - 1))
            ->map(function (int $offset) use ($isMonthly, $start, $keyFormat, $metersByKey): array {
                $date = $isMonthly ? $start->copy()->addMonths($offset) : $start->copy()->addDays($offset);

                return [
                    'label' => $date->format($isMonthly ? 'M' : 'j'),
                    'meters' => $metersByKey[$date->format($keyFormat)] ?? 0.0,
                ];
            })
            ->all();
    }

    /**
     * Whether a fix reflects real movement rather than GPS jitter.
     */
    public static function isMoving(?float $speed, float $stepMeters, ?float $accuracy = null): bool
    {
        if ($speed !== null) {
            return $speed >= self::MIN_MOVING_SPEED_MPS;
        }

        return $stepMeters > max($accuracy ?? 0, self::MIN_MOVING_STEP_M);
    }

    /**
     * The speed to show for a fix: 0 when standing still, unknown or inaccurate.
     */
    public static function displaySpeed(?float $speed, ?float $accuracy = null): float
    {
        if ($speed === null || ($accuracy !== null && $accuracy > self::MAX_ACCURACY_M)) {
            return 0.0;
        }

        return $speed >= self::MIN_MOVING_SPEED_MPS ? $speed : 0.0;
    }

    /**
     * Great-circle distance in meters between two coordinates.
     */
    public static function distanceBetween(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1, sqrt($a)));
    }

    protected function durationSeconds(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->started_at->diffInSeconds($this->ended_at ?? now()));
    }

    protected function averageSpeedMps(): Attribute
    {
        return Attribute::get(fn (): float => $this->duration_seconds > 0 ? $this->distance_m / $this->duration_seconds : 0.0);
    }

    protected function durationLabel(): Attribute
    {
        return Attribute::get(fn (): string => CarbonInterval::seconds($this->duration_seconds)->cascade()->format('%H:%I:%S'));
    }
}
