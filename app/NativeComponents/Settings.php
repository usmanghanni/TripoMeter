<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Vehicle;
use App\SpeedTracker\SpeedTracker;
use App\Support\Appearance;
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

    /**
     * @var array<string, string>
     */
    public const AUTO_RECORD_MODES = ['ask' => 'Ask me', 'auto' => 'Start automatically'];

    /**
     * What to do next when automatic recording couldn't be turned on yet.
     *
     * @var array<string, string>
     */
    public const AUTO_RECORD_HELP = [
        'permission_required' => 'To record automatically, allow Location, Physical activity and Notifications, then tap Save again.',
        'background_location_required' => 'Choose "Allow all the time" for location so trips can start while Tripometer is closed, then come back and tap Save again.',
        'unavailable' => 'Automatic recording needs Android with Google Play services and the latest Tripometer build.',
    ];

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

    /**
     * Beep loudness, 0-100% of the phone's notification volume.
     */
    public float $alertVolume = 100;

    public bool $refuelReminders = true;

    /**
     * Start and stop trips automatically when driving is detected (Android).
     */
    public bool $autoRecord = false;

    /**
     * 'ask' (notify with a Start button) or 'auto' (start by itself).
     */
    public string $autoRecordMode = 'ask';

    /**
     * 'system', 'light' or 'dark'.
     */
    public string $themeMode = 'system';

    public string $accent = 'teal';

    public bool $hasUnsavedChanges = false;

    public function mount(): void
    {
        $setting = Setting::current();

        $this->unit = $setting->unit;
        $this->speedLimitKmh = $setting->speed_limit_kmh;
        $this->speedLimit = $setting->fromKmh($setting->speed_limit_kmh);
        $this->roadLimitAlerts = $setting->road_limit_alerts;
        $this->alertMode = $setting->alert_mode;
        $this->alertVolume = $setting->alert_volume ?? 100;
        $this->refuelReminders = $setting->refuel_reminders ?? true;
        $this->autoRecord = (bool) $setting->auto_record;
        $this->autoRecordMode = $setting->auto_record_mode === 'auto' ? 'auto' : 'ask';
        $this->themeMode = array_key_exists((string) $setting->theme_mode, Appearance::MODES) ? $setting->theme_mode : 'system';
        $this->accent = array_key_exists((string) $setting->accent, Appearance::ACCENTS) ? $setting->accent : 'teal';
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

    public function setAutoRecordMode(string $mode): void
    {
        if (! array_key_exists($mode, self::AUTO_RECORD_MODES) || $mode === $this->autoRecordMode) {
            return;
        }

        $this->autoRecordMode = $mode;
        $this->hasUnsavedChanges = true;
    }

    public function setThemeMode(string $mode): void
    {
        if (! array_key_exists($mode, Appearance::MODES) || $mode === $this->themeMode) {
            return;
        }

        $this->themeMode = $mode;
        $this->hasUnsavedChanges = true;
    }

    public function setAccent(string $accent): void
    {
        if (! array_key_exists($accent, Appearance::ACCENTS) || $accent === $this->accent) {
            return;
        }

        $this->accent = $accent;
        $this->hasUnsavedChanges = true;
    }

    public function testAlert(): void
    {
        SpeedTracker::playAlertSound((int) round($this->alertVolume));
    }

    public function save(): void
    {
        $setting = Setting::current();
        $setting->update($this->draft()->only(['unit', 'speed_limit_kmh', 'road_limit_alerts', 'alert_mode', 'alert_volume', 'refuel_reminders', 'auto_record', 'auto_record_mode', 'theme_mode', 'accent']));

        SpeedTracker::setSpeedUnit($setting->unit);
        Appearance::apply($setting->theme_mode, $setting->accent);
        $this->hasUnsavedChanges = false;

        // The choice is kept even while permissions are pending; the Speed
        // screen re-registers detection once they are granted.
        $autoRecordStatus = SpeedTracker::setAutoRecord($this->autoRecord, mode: $this->autoRecordMode);

        if ($this->autoRecord && $autoRecordStatus !== 'enabled') {
            Dialog::alert('Settings saved', self::AUTO_RECORD_HELP[$autoRecordStatus] ?? self::AUTO_RECORD_HELP['unavailable']);

            return;
        }

        Dialog::toast('Settings saved');
    }

    public function openAbout(): void
    {
        $this->navigate('/about');
    }

    public function openVehicles(): void
    {
        $this->navigate('/vehicles');
    }

    public function openExport(): void
    {
        $this->navigate('/backup/export');
    }

    public function openImport(): void
    {
        $this->navigate('/backup/import');
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
            'alert_volume' => (int) round($this->alertVolume),
            'refuel_reminders' => $this->refuelReminders,
            'auto_record' => $this->autoRecord,
            'auto_record_mode' => $this->autoRecordMode,
            'theme_mode' => $this->themeMode,
            'accent' => $this->accent,
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
            'autoRecordModes' => self::AUTO_RECORD_MODES,
            'themeModes' => Appearance::MODES,
            'accents' => Appearance::ACCENTS,
            'vehicle' => Vehicle::current(),
            'vehicleCount' => Vehicle::count(),
        ]);
    }
}
