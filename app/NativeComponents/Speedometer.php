<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Trip;
use App\SpeedTracker\SpeedTracker;
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

    public function mount(): void
    {
        SpeedTracker::keepScreenOn();
        SpeedTracker::setSpeedUnit(Setting::current()->unit);

        $activeTrip = Trip::whereNull('ended_at')->latest('id')->first();

        if ($activeTrip) {
            $this->tripId = $activeTrip->id;
            $this->watch($activeTrip->watch_id);
            $this->syncFixes();
        }
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

    #[Computed]
    public function trip(): ?Trip
    {
        return $this->tripId ? Trip::find($this->tripId) : null;
    }

    public function startTrip(): void
    {
        $trip = Trip::create(['started_at' => now(), 'watch_id' => (string) Str::uuid()]);

        $this->tripId = $trip->id;
        $this->error = null;
        $this->watch($trip->watch_id);
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
        $this->roadLimitKmh = null;
        $this->lastLimitLookup = null;
    }

    /**
     * Start (or re-attach to) the native GPS watch. Live events drive the display;
     * persistence always goes through the native buffer so nothing is lost while
     * this screen isn't listening.
     */
    private function watch(string $watchId): void
    {
        Geolocation::watchPosition(fineAccuracy: true)
            ->id($watchId)
            ->background()
            ->interval(1000)
            ->locationUpdated(function (object $event): void {
                if (! $event->success) {
                    $this->error = $event->error ?? 'Location unavailable';

                    return;
                }

                $this->error = null;
                $this->speedMps = max(0, $event->speed ?? 0);
                $this->lookUpRoadLimit($event->latitude ?? null, $event->longitude ?? null);
                $this->alertIfOverLimit();
                $this->syncFixes();
            })
            ->start();
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
