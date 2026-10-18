<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\FuelFill;
use App\Models\Trip;
use Illuminate\Support\Carbon;

/**
 * Estimates the car's current odometer: the highest reading logged with a
 * fill-up or expense, plus the distance of trips recorded since.
 *
 * ponytail: unrecorded driving isn't counted, so it reads low until the next
 * logged reading corrects it.
 */
class Odometer
{
    /**
     * @return array{km: float, reading_km: float, read_on: Carbon, driven_since_km: float}|null null before any reading is logged
     */
    public static function current(): ?array
    {
        $fill = FuelFill::orderByDesc('odometer_km')->first();
        $expense = Expense::whereNotNull('odometer_km')->orderByDesc('odometer_km')->first();
        $reading = collect([$fill, $expense])->filter()->sortByDesc('odometer_km')->first();

        if (! $reading) {
            return null;
        }

        $readOn = self::readingMoment($reading instanceof FuelFill ? $reading->filled_on : $reading->spent_on, $reading->created_at);
        $drivenKm = (float) Trip::where('started_at', '>=', $readOn)->sum('distance_m') / 1000;

        return [
            'km' => $reading->odometer_km + $drivenKm,
            'reading_km' => $reading->odometer_km,
            'read_on' => $readOn,
            'driven_since_km' => $drivenKm,
        ];
    }

    /**
     * When a dated reading was taken: its logging time if logged that day,
     * otherwise the start of the date (counts that whole day's trips, so
     * estimates err high rather than low).
     */
    public static function readingMoment(Carbon $date, ?Carbon $loggedAt): Carbon
    {
        return $loggedAt && $loggedAt->isSameDay($date)
            ? $loggedAt
            : $date->copy()->startOfDay();
    }
}
