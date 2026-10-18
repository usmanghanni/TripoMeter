<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Trip;
use App\SpeedTracker\SpeedTracker;
use App\Support\FuelForecast;
use App\Support\NativeTrips;
use App\Support\OilChange;
use App\Support\SwitchesVehicle;
use App\Support\TripMeter;
use App\Tasks\LookUpSpeedLimit;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Geolocation;
use Native\Mobile\Facades\Haptics;
use Native\Mobile\Facades\System;

class Speedometer extends NativeComponent
{
    use SwitchesVehicle {
        switchVehicle as private switchToVehicle;
    }

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

    /**
     * What the active music app is playing; null when media info isn't available.
     *
     * @var array{supported: bool, access: bool, active: bool, playing?: bool, title?: string, artist?: string, app?: string}|null
     */
    public ?array $nowPlaying = null;

    /**
     * Fuel left on this tank, estimated; null without enough fuel history.
     *
     * @var array{remaining_km: float, is_low: bool}|null
     */
    public ?array $fuelForecast = null;

    /**
     * Oil change heads-up when due soon or overdue; null otherwise.
     */
    public ?string $oilChangeNotice = null;

    public function mount(): void
    {
        SpeedTracker::keepScreenOn();
        SpeedTracker::setSpeedUnit(Setting::current()->unit);
        $this->refreshNowPlaying();
        $this->refreshFuelForecast();

        $setting = Setting::current();

        if ($setting->auto_record) {
            // Re-register without prompting; Settings asks for permissions.
            SpeedTracker::setAutoRecord(true, requestPermissions: false, mode: $setting->auto_record_mode ?? 'ask');
        }

        $activeTrip = NativeTrips::sync();

        if ($activeTrip) {
            $this->tripId = $activeTrip->id;
            $this->watchTrip($activeTrip->watch_id);
            $this->syncFixes();

            return;
        }

        $this->watchLiveSpeed();
    }

    /**
     * While away, the trip may have been stopped from the notification or
     * widget, or started by the widget or driving detection.
     */
    public function onResume(): void
    {
        $activeTrip = NativeTrips::sync();

        if ($this->tripId !== null && $activeTrip?->id !== $this->tripId) {
            $this->tripId = null;
            $this->speedMps = 0;
            $this->isOverLimit = false;
            FuelForecast::remindIfLow();
            OilChange::remindIfDue();
            $this->watchLiveSpeed();
        }

        if ($this->tripId === null && $activeTrip) {
            Geolocation::clearWatch(self::LIVE_WATCH_ID);
            $this->tripId = $activeTrip->id;
            $this->watchTrip($activeTrip->watch_id);
        }

        $this->syncFixes();
        $this->refreshFuelForecast();
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
        FuelForecast::remindIfLow();
        OilChange::remindIfDue();
        $this->refreshFuelForecast();

        $this->tripId = null;
        $this->speedMps = 0;
        $this->isOverLimit = false;
        $this->watchLiveSpeed();
    }

    public function resetTripMeter(): void
    {
        TripMeter::reset();
    }

    /**
     * Music controls exist only on Android; asked once, it's a bridge call.
     */
    #[Computed(persist: true)]
    public function isIos(): bool
    {
        return System::isIos();
    }

    #[Poll(2000)]
    public function refreshNowPlaying(): void
    {
        $this->nowPlaying = SpeedTracker::nowPlaying();
    }

    /**
     * @param  'up'|'down'  $direction
     */
    public function adjustVolume(string $direction): void
    {
        SpeedTracker::adjustMusicVolume($direction === 'down' ? 'down' : 'up');
    }

    /**
     * Refreshed on mount, resume and trip stop rather than per render: GPS
     * fixes re-render every second.
     */
    private function refreshFuelForecast(): void
    {
        $setting = Setting::current();
        $forecast = $setting->refuel_reminders ? FuelForecast::current($setting) : null;

        $this->fuelForecast = $forecast ? ['remaining_km' => $forecast['remaining_km'], 'is_low' => $forecast['is_low']] : null;

        $oilChange = OilChange::status($setting);
        $this->oilChangeNotice = $oilChange && $oilChange['is_due_soon'] ? 'Oil change '.lcfirst(OilChange::summary($oilChange)) : null;
    }

    public function mediaCommand(string $command): void
    {
        if (! in_array($command, ['toggle', 'next', 'previous'], true)) {
            return;
        }

        SpeedTracker::mediaCommand($command);
        $this->refreshNowPlaying();
    }

    public function grantMediaAccess(): void
    {
        SpeedTracker::openMediaAccessSettings();
    }

    /**
     * Range and oil change notices are per vehicle.
     */
    public function switchVehicle(int $vehicleId): void
    {
        $this->switchToVehicle($vehicleId);
        $this->refreshFuelForecast();
    }

    public function openExpenses(): void
    {
        $this->navigate('/expenses');
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
        $this->speedMps = Trip::displaySpeed($event->speed ?? null, $event->accuracy ?? null);

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
            SpeedTracker::playAlertSound($setting->alert_volume ?? 100);
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
