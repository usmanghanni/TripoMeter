<?php

namespace App\SpeedTracker;

class SpeedTracker
{
    /**
     * Unit ('kmh' or 'mph') for the native trip notification.
     */
    public static function setSpeedUnit(string $unit): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.SetUnit', json_encode(['unit' => $unit]));
        }
    }

    /**
     * Keep the screen awake while the app is in the foreground.
     */
    public static function keepScreenOn(bool $enabled = true): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.KeepScreenOn', json_encode(['enabled' => $enabled]));
        }
    }

    /**
     * Play a short alert beep (Android tone generator / iOS system sound).
     */
    public static function playAlertSound(): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.PlayAlert', '{}');
        }
    }
}
