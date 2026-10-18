<?php

namespace App\Support;

use App\Models\FuelFill;
use App\Models\Trip;
use App\Models\TripPoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * JSON backup of trips (with every recorded point) and fuel fill-ups.
 *
 * Imports skip anything already on the device: a trip with the same start
 * time, or a fill-up with the same date and odometer reading.
 */
class TripBackup
{
    public const FORMAT = 'tripometer-backup';

    public const VERSION = 1;

    private const POINT_INSERT_CHUNK = 500;

    /**
     * @param  list<int>  $tripIds
     * @return array{format: string, version: int, exported_at: string, trips: list<array<string, mixed>>, fuel_fills: list<array<string, mixed>>}
     */
    public static function build(array $tripIds, bool $includeFuel): array
    {
        $trips = Trip::whereKey($tripIds)
            ->whereNotNull('ended_at')
            ->with('points')
            ->orderBy('started_at')
            ->get()
            ->map(fn (Trip $trip): array => [
                'started_at' => $trip->started_at->toIso8601String(),
                'ended_at' => $trip->ended_at->toIso8601String(),
                'distance_m' => $trip->distance_m,
                'max_speed_mps' => $trip->max_speed_mps,
                'title' => $trip->title,
                'notes' => $trip->notes,
                'points' => $trip->points->map(fn (TripPoint $point): array => [
                    'timestamp' => $point->recorded_at->getTimestampMs(),
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                    'speed_mps' => $point->speed_mps,
                    'accuracy' => $point->accuracy,
                    'heading' => $point->heading,
                    'altitude' => $point->altitude,
                ])->all(),
            ])
            ->all();

        $fuelFills = $includeFuel
            ? FuelFill::orderBy('odometer_km')->get()->map(fn (FuelFill $fill): array => [
                'filled_on' => $fill->filled_on->toDateString(),
                'odometer_km' => $fill->odometer_km,
                'liters' => $fill->liters,
                'total_cost' => $fill->total_cost,
                'is_full_tank' => $fill->is_full_tank,
            ])->all()
            : [];

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'trips' => $trips,
            'fuel_fills' => $fuelFills,
        ];
    }

    /**
     * Write a backup to app storage and return its path.
     *
     * @param  array<string, mixed>  $backup
     */
    public static function write(array $backup): string
    {
        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Only the latest export is kept; the user saves it elsewhere.
        foreach (glob("{$directory}/*.json") ?: [] as $oldExport) {
            @unlink($oldExport);
        }

        $path = $directory.'/'.self::fileName();
        file_put_contents($path, json_encode($backup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return $path;
    }

    public static function fileName(): string
    {
        return 'tripometer-'.now()->format('Y-m-d-His').'.json';
    }

    /**
     * Read and validate a backup file.
     *
     * @return array{format: string, version: int, trips: list<array<string, mixed>>, fuel_fills: list<array<string, mixed>>}
     *
     * @throws InvalidArgumentException when the file isn't a readable Tripometer backup
     */
    public static function read(string $path): array
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;
        $backup = $contents === false ? null : json_decode($contents, true);

        if (! is_array($backup) || ($backup['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException('This file is not a Tripometer backup.');
        }

        $validator = Validator::make($backup, [
            'version' => ['required', 'integer', 'max:'.self::VERSION],
            'trips' => ['present', 'array'],
            'trips.*.started_at' => ['required', 'date'],
            'trips.*.ended_at' => ['nullable', 'date'],
            'trips.*.distance_m' => ['nullable', 'numeric'],
            'trips.*.max_speed_mps' => ['nullable', 'numeric'],
            'trips.*.points' => ['nullable', 'array'],
            'fuel_fills' => ['present', 'array'],
            'fuel_fills.*.filled_on' => ['required', 'date'],
            'fuel_fills.*.odometer_km' => ['required', 'numeric'],
            'fuel_fills.*.liters' => ['required', 'numeric'],
        ], [
            'version.max' => 'This backup was made by a newer version of Tripometer. Update the app first.',
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }

        $backup['trips'] = array_values($backup['trips']);
        $backup['fuel_fills'] = array_values($backup['fuel_fills']);

        return $backup;
    }

    /**
     * What a backup contains, for choosing what to import.
     *
     * @param  array{trips: list<array<string, mixed>>, fuel_fills: list<array<string, mixed>>}  $backup
     * @return array{trips: list<array{index: int, title: string|null, started_at: string, distance_m: float, points: int, exists: bool}>, fuel_fills: int, new_fuel_fills: int}
     */
    public static function summarize(array $backup): array
    {
        return [
            'trips' => array_map(fn (array $trip, int $index): array => [
                'index' => $index,
                'title' => $trip['title'] ?? null,
                'started_at' => self::localTime($trip['started_at'])->toDateTimeString(),
                'distance_m' => (float) ($trip['distance_m'] ?? 0),
                'points' => count($trip['points'] ?? []),
                'exists' => self::tripExists($trip),
            ], $backup['trips'], array_keys($backup['trips'])),
            'fuel_fills' => count($backup['fuel_fills']),
            'new_fuel_fills' => count(array_filter($backup['fuel_fills'], fn (array $fill): bool => ! self::fuelFillExists($fill))),
        ];
    }

    /**
     * Import the chosen trips (by their index in the backup) and, optionally,
     * the fuel log, skipping anything already on the device.
     *
     * @param  array{trips: list<array<string, mixed>>, fuel_fills: list<array<string, mixed>>}  $backup
     * @param  list<int>  $tripIndexes
     * @return array{trips: int, fuel_fills: int, skipped: int}
     */
    public static function import(array $backup, array $tripIndexes, bool $includeFuel): array
    {
        return DB::transaction(function () use ($backup, $tripIndexes, $includeFuel): array {
            $result = ['trips' => 0, 'fuel_fills' => 0, 'skipped' => 0];

            foreach (array_unique($tripIndexes) as $index) {
                $data = $backup['trips'][$index] ?? null;

                if ($data === null) {
                    continue;
                }

                if (self::tripExists($data)) {
                    $result['skipped']++;

                    continue;
                }

                self::importTrip($data);
                $result['trips']++;
            }

            if ($includeFuel) {
                foreach ($backup['fuel_fills'] as $fill) {
                    if (self::fuelFillExists($fill)) {
                        $result['skipped']++;

                        continue;
                    }

                    FuelFill::create([
                        'filled_on' => Carbon::parse($fill['filled_on'])->toDateString(),
                        'odometer_km' => (float) $fill['odometer_km'],
                        'liters' => (float) $fill['liters'],
                        'total_cost' => (float) ($fill['total_cost'] ?? 0),
                        'is_full_tank' => (bool) ($fill['is_full_tank'] ?? true),
                    ]);
                    $result['fuel_fills']++;
                }
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function importTrip(array $data): void
    {
        $startedAt = self::localTime($data['started_at']);

        $trip = Trip::create([
            'started_at' => $startedAt,
            'ended_at' => isset($data['ended_at']) ? self::localTime($data['ended_at']) : $startedAt,
            'distance_m' => (float) ($data['distance_m'] ?? 0),
            'max_speed_mps' => (float) ($data['max_speed_mps'] ?? 0),
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $dateFormat = $trip->getDateFormat();

        collect($data['points'] ?? [])
            ->filter(fn (mixed $point): bool => is_array($point) && is_numeric($point['latitude'] ?? null) && is_numeric($point['longitude'] ?? null) && is_numeric($point['timestamp'] ?? null))
            ->map(fn (array $point): array => [
                'trip_id' => $trip->id,
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'speed_mps' => self::floatOrNull($point['speed_mps'] ?? null),
                'accuracy' => self::floatOrNull($point['accuracy'] ?? null),
                'heading' => self::floatOrNull($point['heading'] ?? null),
                'altitude' => self::floatOrNull($point['altitude'] ?? null),
                'recorded_at' => Carbon::createFromTimestampMs((int) $point['timestamp'])->setTimezone(config('app.timezone'))->format($dateFormat),
            ])
            ->chunk(self::POINT_INSERT_CHUNK)
            ->each(fn ($chunk) => TripPoint::insert($chunk->values()->all()));
    }

    /**
     * @param  array<string, mixed>  $trip
     */
    private static function tripExists(array $trip): bool
    {
        return Trip::where('started_at', self::localTime($trip['started_at']))->exists();
    }

    /**
     * @param  array<string, mixed>  $fill
     */
    private static function fuelFillExists(array $fill): bool
    {
        return FuelFill::whereDate('filled_on', Carbon::parse($fill['filled_on'])->toDateString())
            ->where('odometer_km', (float) $fill['odometer_km'])
            ->exists();
    }

    private static function localTime(string $time): Carbon
    {
        return Carbon::parse($time)->setTimezone(config('app.timezone'));
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
