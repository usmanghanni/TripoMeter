<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\SpeedTracker\SpeedTracker;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Settings extends NativeComponent
{
    /**
     * @var list<string>
     */
    public const UNITS = ['kmh', 'mph'];

    public int $unitIndex = 0;

    /**
     * Personal speed limit in the selected unit (stored as km/h).
     */
    public float $speedLimit = 0;

    public bool $roadLimitAlerts = true;

    public int $alertModeIndex = 0;

    public function mount(): void
    {
        $setting = Setting::current();

        $this->unitIndex = (int) array_search($setting->unit, self::UNITS, true);
        $this->speedLimit = $setting->fromKmh($setting->speed_limit_kmh);
        $this->roadLimitAlerts = $setting->road_limit_alerts;
        $this->alertModeIndex = (int) array_search($setting->alert_mode, Setting::ALERT_MODES, true);
    }

    public function navTitle(): string
    {
        return 'Settings';
    }

    public function updatedUnitIndex(): void
    {
        $setting = Setting::current();
        $setting->update(['unit' => self::UNITS[$this->unitIndex] ?? 'kmh']);

        $this->speedLimit = $setting->fromKmh($setting->speed_limit_kmh);
        SpeedTracker::setSpeedUnit($setting->unit);
    }

    public function updatedSpeedLimit(): void
    {
        $setting = Setting::current();
        $setting->update(['speed_limit_kmh' => max(0, $setting->toKmh($this->speedLimit))]);
    }

    public function updatedRoadLimitAlerts(): void
    {
        Setting::current()->update(['road_limit_alerts' => $this->roadLimitAlerts]);
    }

    public function updatedAlertModeIndex(): void
    {
        Setting::current()->update(['alert_mode' => Setting::ALERT_MODES[$this->alertModeIndex] ?? 'vibrate']);
    }

    public function render(): View
    {
        return view('native.settings', [
            'limitLabel' => (int) round($this->speedLimit),
            'unitLabel' => $this->unitIndex === 1 ? 'mph' : 'km/h',
            'sliderMax' => $this->unitIndex === 1 ? 125 : 200,
        ]);
    }
}
