<?php

namespace App\SpeedTracker;

use Illuminate\Support\Facades\Log;

class SpeedTracker
{
    /**
     * What any music app is playing (Android media sessions; unsupported on iOS).
     *
     * `available` is false when this build doesn't contain the plugin's native
     * code (e.g. NativePHP Jump or an app built before the plugin changed), and
     * `error` is set when the native call failed.
     *
     * @return array{available: bool, supported: bool, access: bool, active: bool, playing?: bool, title?: string, artist?: string, app?: string, error?: string}|null
     */
    public static function nowPlaying(): ?array
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $unavailable = ['available' => false, 'supported' => false, 'access' => false, 'active' => false];

        if (function_exists('nativephp_can') && ! nativephp_can('SpeedTracker.NowPlaying')) {
            return $unavailable;
        }

        $raw = nativephp_call('SpeedTracker.NowPlaying', '{}');

        if ($raw === null) {
            return $unavailable;
        }

        $result = json_decode((string) $raw, true);
        $result = is_array($result) && ! isset($result['supported']) && is_array($result['data'] ?? null) ? $result['data'] : $result;

        if (! is_array($result) || ! isset($result['supported'])) {
            Log::warning('SpeedTracker.NowPlaying returned an unexpected response', ['response' => $raw]);

            return [...$unavailable, 'available' => true, 'supported' => true, 'error' => is_array($result) ? (string) ($result['message'] ?? 'Unexpected response') : 'Unexpected response'];
        }

        return ['available' => true, ...$result];
    }

    /**
     * @param  'play'|'pause'|'toggle'|'next'|'previous'  $command
     */
    public static function mediaCommand(string $command): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.MediaCommand', json_encode(['command' => $command]));
        }
    }

    /**
     * Open the system screen where the user grants media access (Android).
     */
    public static function openMediaAccessSettings(): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.OpenMediaAccessSettings', '{}');
        }
    }

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
