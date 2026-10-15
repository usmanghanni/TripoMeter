<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\SpeedTracker\SpeedTracker;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Edits a draft of the settings; nothing is written until Save is tapped.
 */
class Settings extends NativeComponent
{
    /**
     * @var array<string, string>
     */
    public const UNITS = ['kmh' => 'km/h', 'mph' => 'mph'];

    /**
     * @var array<string, string>
     */
    public const ALERT_MODES = ['vibrate' => 'Vibrate', 'sound' => 'Sound', 'both' => 'Both'];

    public string $unit = 'kmh';

    /**
     * Personal speed limit as shown on the slider, in the selected unit.
     */
    public float $speedLimit = 0;

    /**
     * The same limit in km/h, the stored unit. Kept separately so switching
     * units converts the slider instead of reinterpreting its number.
     */
    public int $speedLimitKmh = 0;

    public bool $roadLimitAlerts = true;

    public string $alertMode = 'vibrate';

    public bool $hasUnsavedChanges = false;

    public function mount(): void
    {
        $setting = Setting::current();

        $this->unit = $setting->unit;
        $this->speedLimitKmh = $setting->speed_limit_kmh;
        $this->speedLimit = $setting->fromKmh($setting->speed_limit_kmh);
        $this->roadLimitAlerts = $setting->road_limit_alerts;
        $this->alertMode = $setting->alert_mode;
    }

    public function navTitle(): string
    {
        return 'Settings';
    }

    /**
     * Any bound control changed.
     */
    public function updated(): void
    {
        $this->hasUnsavedChanges = true;
    }

    /**
     * Unit and alert type use plain buttons (press events) rather than a
     * button-group: the group's change event did not persist on device.
     */
    public function setUnit(string $unit): void
    {
        if (! array_key_exists($unit, self::UNITS) || $unit === $this->unit) {
            return;
        }

        $this->unit = $unit;
        $this->speedLimit = $this->draft()->fromKmh($this->speedLimitKmh);
        $this->hasUnsavedChanges = true;
    }

    public function setAlertMode(string $alertMode): void
    {
        if (! array_key_exists($alertMode, self::ALERT_MODES) || $alertMode === $this->alertMode) {
            return;
        }

        $this->alertMode = $alertMode;
        $this->hasUnsavedChanges = true;
    }

    public function updatedSpeedLimit(): void
    {
        $this->speedLimitKmh = max(0, $this->draft()->toKmh($this->speedLimit));
    }

    public function save(): void
    {
        $setting = Setting::current();
        $setting->update($this->draft()->only(['unit', 'speed_limit_kmh', 'road_limit_alerts', 'alert_mode']));

        SpeedTracker::setSpeedUnit($setting->unit);
        Dialog::toast('Settings saved');

        $this->hasUnsavedChanges = false;
    }

    /**
     * The settings as currently edited, unsaved.
     */
    private function draft(): Setting
    {
        return new Setting([
            'unit' => $this->unit,
            'speed_limit_kmh' => $this->speedLimitKmh,
            'road_limit_alerts' => $this->roadLimitAlerts,
            'alert_mode' => $this->alertMode,
        ]);
    }

    public function render(): View
    {
        return view('native.settings', [
            'limitLabel' => (int) round($this->speedLimit),
            'unitLabel' => self::UNITS[$this->unit],
            'sliderMax' => $this->unit === 'mph' ? 125 : 200,
            'units' => self::UNITS,
            'alertModes' => self::ALERT_MODES,
        ]);
    }
}
