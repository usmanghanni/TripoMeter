<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Trip;
use App\SpeedTracker\SpeedTracker;
use App\Support\TripMeter;
use App\Tasks\LookUpSpeedLimit;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Geolocation;
use Native\Mobile\Facades\Haptics;

class Speedometer extends NativeComponent
{
    private const LIMIT_LOOKUP_DISTANCE_M = 150;

    private const LIMIT_LOOKUP_SECONDS = 60;

    /**
     * Foreground watch that shows live speed while no trip is recording.
     */
    public const LIVE_WATCH_ID = 'live-speed';

    public ?int $tripId = null;

    public float $speedMps = 0;

    public bool $isOverLimit = false;

    /**
     * Posted limit of the current road in km/h, null when unknown.
     */
    public ?int $roadLimitKmh = null;

    /**
     * @var array{latitude: float, longitude: float, at: int}|null
     */
    public ?array $lastLimitLookup = null;

    public bool $isLookingUpLimit = false;

    public ?string $error = null;

    /**
     * Full-screen, extra-large display (tab and nav bars hidden by the layout).
     */
    public bool $isHudMode = false;

    public function mount(): void
    {
        SpeedTracker::keepScreenOn();
        SpeedTracker::setSpeedUnit(Setting::current()->unit);

        $activeTrip = Trip::whereNull('ended_at')->latest('id')->first();

        if ($activeTrip) {
            $this->tripId = $activeTrip->id;
            $this->watchTrip($activeTrip->watch_id);
            $this->syncFixes();

            return;
        }

        $this->watchLiveSpeed();
    }

    public function onResume(): void
    {
        $this->syncFixes();
    }

    public function navTitle(): string
    {
        return 'Speed';
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    /**
     * @return array{meters: float, max_mps: float, since: int, last: array{latitude: float, longitude: float}|null}
     */
    #[Computed]
    public function tripMeter(): array
    {
        return TripMeter::state();
    }

    #[Computed]
    public function trip(): ?Trip
    {
        return $this->tripId ? Trip::find($this->tripId) : null;
    }

    public function startTrip(): void
    {
        Geolocation::clearWatch(self::LIVE_WATCH_ID);

        $trip = Trip::create(['started_at' => now(), 'watch_id' => (string) Str::uuid()]);

        $this->tripId = $trip->id;
        $this->error = null;
        $this->watchTrip($trip->watch_id);
    }

    public function stopTrip(): void
    {
        if (! $this->trip) {
            return;
        }

        $this->syncFixes();
        Geolocation::stopBackgroundWatch($this->trip->watch_id, clearBuffer: true);
        $this->trip->update(['ended_at' => now()]);

        $this->tripId = null;
        $this->speedMps = 0;
        $this->isOverLimit = false;
        $this->watchLiveSpeed();
    }

    public function resetTripMeter(): void
    {
        TripMeter::reset();
    }

    public function toggleHud(): void
    {
        $this->isHudMode = ! $this->isHudMode;
    }

    /**
     * Start (or re-attach to) the background trip watch. Live events drive the
     * display; persistence always goes through the native buffer so nothing is
     * lost while this screen isn't listening.
     */
    private function watchTrip(string $watchId): void
    {
        Geolocation::watchPosition(fineAccuracy: true)
            ->id($watchId)
            ->background()
            ->interval(1000)
            ->locationUpdated(function (object $event): void {
                $this->handleFix($event);
                $this->syncFixes();
            })
            ->start();
    }

    /**
     * Foreground-only watch for live speed without recording; it stops by
     * itself when this screen unmounts.
     */
    private function watchLiveSpeed(): void
    {
        Geolocation::watchPosition(fineAccuracy: true)
            ->id(self::LIVE_WATCH_ID)
            ->interval(1000)
            ->locationUpdated(fn (object $event) => $this->handleFix($event))
            ->start();
    }

    private function handleFix(object $event): void
    {
        if (! $event->success) {
            $this->error = $event->error ?? 'Location unavailable';

            return;
        }

        $this->error = null;
        $this->speedMps = max(0, $event->speed ?? 0);

        if (isset($event->latitude, $event->longitude)) {
            TripMeter::record($event->latitude, $event->longitude, $event->accuracy ?? null, $event->speed ?? null);
        }

        $this->lookUpRoadLimit($event->latitude ?? null, $event->longitude ?? null);
        $this->alertIfOverLimit();
    }

    /**
     * Refresh the posted road limit in the background after moving
     * LIMIT_LOOKUP_DISTANCE_M or every LIMIT_LOOKUP_SECONDS (keeps Overpass usage polite).
     */
    private function lookUpRoadLimit(?float $latitude, ?float $longitude): void
    {
        if ($latitude === null || $longitude === null || $this->isLookingUpLimit) {
            return;
        }

        $last = $this->lastLimitLookup;
        $isDue = $last === null
            || time() - $last['at'] >= self::LIMIT_LOOKUP_SECONDS
            || Trip::distanceBetween($last['latitude'], $last['longitude'], $latitude, $longitude) >= self::LIMIT_LOOKUP_DISTANCE_M;

        if (! $isDue) {
            return;
        }

        $this->isLookingUpLimit = true;
        $this->lastLimitLookup = ['latitude' => $latitude, 'longitude' => $longitude, 'at' => time()];

        LookUpSpeedLimit::dispatch($latitude, $longitude)
            ->timeout(15)
            ->finished(function (?int $limitKmh): void {
                $this->isLookingUpLimit = false;
                $this->roadLimitKmh = $limitKmh;
                $this->alertIfOverLimit();
            })
            ->failed(function (): void {
                $this->isLookingUpLimit = false;
            });
    }

    /**
     * Vibrate and/or beep (per settings) once each time the speed crosses above the limit.
     */
    private function alertIfOverLimit(): void
    {
        $setting = Setting::current();
        $wasOverLimit = $this->isOverLimit;
        $this->isOverLimit = $setting->isOverLimit($this->speedMps, $this->roadLimitKmh);

        if (! $this->isOverLimit || $wasOverLimit) {
            return;
        }

        if ($setting->alertVibrates()) {
            Haptics::vibrate();
        }

        if ($setting->alertPlaysSound()) {
            SpeedTracker::playAlertSound();
        }
    }

    private function syncFixes(): void
    {
        if (! $this->trip) {
            return;
        }

        $buffer = Geolocation::drainWatch($this->trip->watch_id);

        if ($buffer['fixes'] === []) {
            return;
        }

        $this->trip->recordFixes($buffer['fixes']);
        Geolocation::trimWatch($this->trip->watch_id, $buffer['cursor']);
    }

    public function render(): View
    {
        return view('native.speedometer');
    }
}
