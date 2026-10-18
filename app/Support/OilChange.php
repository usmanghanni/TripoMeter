<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Setting;
use App\SpeedTracker\SpeedTracker;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * When the next oil change is due: the last logged oil change plus the
 * interval in km (against the estimated odometer) or months, whichever
 * comes first.
 */
class OilChange
{
    /**
     * "Due soon" this many km or days before the due point.
     */
    public const SOON_KM = 500;

    public const SOON_DAYS = 14;

    private const REMINDED_CACHE_KEY = 'oil_change_reminded_expense_id';

    /**
     * @return array{last_on: CarbonInterface, last_km: float|null, due_km: float|null, remaining_km: float|null, due_on: CarbonInterface|null, days_left: int|null, fraction_left: float, is_due_soon: bool, is_overdue: bool}|null null until an oil change is logged
     */
    public static function status(?Setting $setting = null): ?array
    {
        $setting ??= Setting::current();
        $last = self::lastOilChange();

        if (! $last) {
            return null;
        }

        $intervalKm = $setting->oil_change_interval_km ?? 5000;
        $intervalMonths = $setting->oil_change_interval_months ?? 6;
        $odometer = Odometer::current();

        $dueKm = $last->odometer_km !== null && $intervalKm > 0 ? $last->odometer_km + $intervalKm : null;
        $remainingKm = $dueKm !== null && $odometer ? $dueKm - $odometer['km'] : null;
        $dueOn = $intervalMonths > 0 ? $last->spent_on->copy()->addMonthsNoOverflow($intervalMonths) : null;
        $daysLeft = $dueOn ? (int) floor(today()->diffInDays($dueOn, false)) : null;

        $fractions = array_filter([
            $remainingKm !== null ? $remainingKm / $intervalKm : null,
            $dueOn ? $daysLeft / max(1, $last->spent_on->diffInDays($dueOn)) : null,
        ], fn (?float $fraction): bool => $fraction !== null);

        return [
            'last_on' => $last->spent_on,
            'last_km' => $last->odometer_km,
            'due_km' => $dueKm,
            'remaining_km' => $remainingKm,
            'due_on' => $dueOn,
            'days_left' => $daysLeft,
            'fraction_left' => $fractions === [] ? 1.0 : max(0.0, min(1.0, min($fractions))),
            'is_due_soon' => ($remainingKm !== null && $remainingKm <= self::SOON_KM) || ($daysLeft !== null && $daysLeft <= self::SOON_DAYS),
            'is_overdue' => ($remainingKm !== null && $remainingKm <= 0) || ($daysLeft !== null && $daysLeft <= 0),
        ];
    }

    /**
     * Notify once per oil change when the next one is due soon.
     *
     * @return bool whether a reminder was sent
     */
    public static function remindIfDue(): bool
    {
        $status = self::status();
        $lastId = self::lastOilChange()?->id;

        if (! $status || ! $status['is_due_soon'] || Cache::get(self::REMINDED_CACHE_KEY) === $lastId) {
            return false;
        }

        Cache::forever(self::REMINDED_CACHE_KEY, $lastId);
        SpeedTracker::notify('Oil change due', self::summary($status));

        return true;
    }

    /**
     * "Due in 420 km · due by 12 Mar 2027" / "120 km overdue".
     *
     * @param  array{remaining_km: float|null, due_on: CarbonInterface|null, days_left: int|null}  $status
     */
    public static function summary(array $status): string
    {
        $parts = [];

        if ($status['remaining_km'] !== null) {
            $parts[] = $status['remaining_km'] > 0
                ? 'due in '.number_format($status['remaining_km']).' km'
                : number_format(abs($status['remaining_km'])).' km overdue';
        }

        if ($status['due_on'] !== null) {
            $parts[] = ($status['days_left'] > 0 ? 'due by ' : 'overdue since ').$status['due_on']->format('j M Y');
        }

        return $parts === [] ? 'Log the odometer with your next oil change.' : ucfirst(implode(' · ', $parts));
    }

    private static function lastOilChange(): ?Expense
    {
        return Expense::where('category', 'oil_change')
            ->orderByDesc('spent_on')
            ->orderByDesc('odometer_km')
            ->first();
    }
}
