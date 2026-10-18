<?php

namespace App\Support;

use App\Models\Trip;
use App\SpeedTracker\SpeedTracker;
use Illuminate\Support\Carbon;
use Native\Mobile\Facades\Geolocation;

/**
 * Saves trips the native side recorded on its own: started from the home
 * screen widget or by driving detection, or stopped from the widget or the
 * notification while the app wasn't open.
 *
 * Buffered fixes carry their trip's watch id, so several trips recorded
 * before the app was opened are kept apart.
 */
class NativeTrips
{
    /**
     * Automatically detected trips shorter than this are dropped as false
     * starts (e.g. a bus ride across the car park).
     */
    public const MIN_AUTO_TRIP_M = 300;

    /**
     * Save natively finished trips, adopt a natively started one, and return
     * the trip being recorded now, if any.
     */
    public static function sync(): ?Trip
    {
        $state = SpeedTracker::nativeTrips();

        if ($state !== null && ($state['finished'] !== [] || $state['active'] !== null)) {
            $watchId = $state['active']['id'] ?? $state['finished'][0]['id'];
            $buffer = Geolocation::drainWatch($watchId);
            $fixesByTrip = collect($buffer['fixes'])->groupBy(fn (array $fix): string => (string) ($fix['watch_id'] ?? ''));

            foreach ($state['finished'] as $native) {
                $trip = self::tripFor($native);
                $trip->recordFixes($fixesByTrip->pull($native['id'], collect())->all());

                if ($trip->ended_at === null) {
                    $trip->update(['ended_at' => self::time($native['ended_at'] ?? null) ?? now()]);
                }

                if (($native['source'] ?? null) === 'auto' && $trip->distance_m < self::MIN_AUTO_TRIP_M) {
                    $trip->delete();
                }

                SpeedTracker::acknowledgeNativeTrip($native['id']);
            }

            if ($state['active'] !== null) {
                self::tripFor($state['active'])->recordFixes([
                    ...$fixesByTrip->pull($state['active']['id'], collect())->all(),
                    ...$fixesByTrip->pull('', collect())->all(),
                ]);
            }

            if ($buffer['fixes'] !== []) {
                Geolocation::trimWatch($watchId, $buffer['cursor']);
            }
        }

        return Trip::whereNull('ended_at')->latest('id')->first();
    }

    /**
     * @param  array{id: string, started_at?: int|null, source?: string|null}  $native
     */
    private static function tripFor(array $native): Trip
    {
        return Trip::firstOrCreate(
            ['watch_id' => $native['id']],
            [
                'started_at' => self::time($native['started_at'] ?? null) ?? now(),
                'title' => ($native['source'] ?? null) === 'auto' ? 'Auto-recorded trip' : null,
            ],
        );
    }

    private static function time(mixed $milliseconds): ?Carbon
    {
        return is_numeric($milliseconds)
            ? Carbon::createFromTimestampMs((int) $milliseconds)->setTimezone(config('app.timezone'))
            : null;
    }
}
