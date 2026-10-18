<?php

namespace App\Support;

use App\Models\FuelFill;
use App\Models\Setting;
use App\Models\Trip;
use App\SpeedTracker\SpeedTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Estimates how far the car can still go since the last full tank, from the
 * fuel log's average economy and the distance of trips recorded since then.
 *
 * ponytail: only recorded trips count, so driving without recording makes the
 * estimate optimistic. The full-tank range uses the tank size when set,
 * otherwise the average distance driven between full fills.
 */
class FuelForecast
{
    /**
     * Remind when the estimated range left is at or below this share of a full tank.
     */
    public const LOW_FRACTION = 0.15;

    private const REMINDED_FILL_CACHE_KEY = 'refuel_reminded_fill_id';

    /**
     * @return array{since: Carbon, driven_km: float, full_range_km: float, remaining_km: float, fraction_left: float, is_low: bool}|null null until there is enough fuel history
     */
    public static function current(?Setting $setting = null): ?array
    {
        $setting ??= Setting::current();
        $kmPerLiter = FuelFill::economyReport()['km_per_liter'];
        $lastFull = FuelFill::where('is_full_tank', true)->orderByDesc('odometer_km')->first();

        if (! $kmPerLiter || ! $lastFull) {
            return null;
        }

        $fullRangeKm = $setting->tank_capacity_l > 0
            ? $setting->tank_capacity_l * $kmPerLiter
            : self::averageKmBetweenFullFills();

        if (! $fullRangeKm) {
            return null;
        }

        $since = self::fillMoment($lastFull);
        $toppedUpKm = (float) FuelFill::where('odometer_km', '>', $lastFull->odometer_km)->sum('liters') * $kmPerLiter;
        $rangeKm = $setting->tank_capacity_l > 0 ? min($fullRangeKm, $fullRangeKm + $toppedUpKm) : $fullRangeKm + $toppedUpKm;
        $drivenKm = (float) Trip::where('started_at', '>=', $since)->sum('distance_m') / 1000;
        $remainingKm = max(0.0, $rangeKm - $drivenKm);
        $fractionLeft = $remainingKm / $fullRangeKm;

        return [
            'since' => $since,
            'driven_km' => $drivenKm,
            'full_range_km' => $fullRangeKm,
            'remaining_km' => $remainingKm,
            'fraction_left' => min(1.0, $fractionLeft),
            'is_low' => $fractionLeft <= self::LOW_FRACTION,
        ];
    }

    /**
     * Notify once per full tank when the estimate runs low.
     *
     * @return bool whether a reminder was sent
     */
    public static function remindIfLow(): bool
    {
        $setting = Setting::current();

        if (! $setting->refuel_reminders) {
            return false;
        }

        $forecast = self::current($setting);
        $lastFullId = FuelFill::where('is_full_tank', true)->orderByDesc('odometer_km')->value('id');

        if (! $forecast || ! $forecast['is_low'] || Cache::get(self::REMINDED_FILL_CACHE_KEY) === $lastFullId) {
            return false;
        }

        Cache::forever(self::REMINDED_FILL_CACHE_KEY, $lastFullId);

        $remaining = number_format($setting->distance($forecast['remaining_km'] * 1000)).' '.$setting->distanceUnit();
        SpeedTracker::notify('Time to refuel', "About {$remaining} left on this tank, based on your fuel history.");

        return true;
    }

    /**
     * Average odometer distance between consecutive full fills.
     */
    private static function averageKmBetweenFullFills(): ?float
    {
        $odometers = FuelFill::where('is_full_tank', true)->orderBy('odometer_km')->pluck('odometer_km');

        if ($odometers->count() < 2) {
            return null;
        }

        return ($odometers->last() - $odometers->first()) / ($odometers->count() - 1);
    }

    private static function fillMoment(FuelFill $fill): Carbon
    {
        return Odometer::readingMoment($fill->filled_on, $fill->created_at);
    }
}
