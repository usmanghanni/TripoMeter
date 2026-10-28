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
     *
     * @param  int  $volume  0-100, relative to the device's notification volume (Android)
     */
    public static function playAlertSound(int $volume = 100): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.PlayAlert', json_encode(['volume' => max(0, min(100, $volume))]));
        }
    }

    /**
     * Step the device's music volume up or down, showing the system volume UI.
     *
     * @param  'up'|'down'  $direction
     */
    public static function adjustMusicVolume(string $direction): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('SpeedTracker.AdjustVolume', json_encode(['direction' => $direction]));
        }
    }

    /**
     * Post a local notification (e.g. the refuel reminder).
     */
    public static function notify(string $title, string $body): void
    {
        if (self::can('SpeedTracker.Notify')) {
            nativephp_call('SpeedTracker.Notify', json_encode(['title' => $title, 'body' => $body]));
        }
    }

    /**
     * Ask the user where to save a copy of a file (Android "Save as" / iOS
     * Files). The result arrives as an Events\FileSaved event.
     *
     * Returns false when this build has no file support (older build or Jump).
     */
    public static function saveFile(string $path, string $fileName): bool
    {
        if (! self::can('SpeedTracker.SaveFile')) {
            return false;
        }

        nativephp_call('SpeedTracker.SaveFile', json_encode(['path' => $path, 'name' => $fileName, 'mime' => 'application/json']));

        return true;
    }

    /**
     * Let the user choose a file to open. The result arrives as an
     * Events\FilePicked event with a readable path.
     *
     * Returns false when this build has no file support (older build or Jump).
     */
    public static function pickFile(): bool
    {
        if (! self::can('SpeedTracker.PickFile')) {
            return false;
        }

        nativephp_call('SpeedTracker.PickFile', json_encode(['mime' => 'application/json']));

        return true;
    }

    /**
     * Trips the native side started or stopped while PHP wasn't running them
     * (widget, notification, driving detection). Null where unsupported.
     *
     * @return array{active: array{id: string, started_at: int, source: string}|null, finished: list<array{id: string, started_at: int, ended_at: int, source: string}>}|null
     */
    public static function nativeTrips(): ?array
    {
        if (! self::can('SpeedTracker.NativeTrips')) {
            return null;
        }

        $result = self::decode(nativephp_call('SpeedTracker.NativeTrips', '{}'));

        if (! is_array($result) || ! array_key_exists('finished', $result)) {
            return null;
        }

        return [
            'active' => is_array($result['active'] ?? null) ? $result['active'] : null,
            'finished' => array_values(array_filter((array) $result['finished'], 'is_array')),
        ];
    }

    /**
     * The installed build's version; null where the build can't tell (older
     * builds, NativePHP Jump, tests).
     *
     * @return array{version: string, code: int, debug: bool}|null
     */
    public static function appInfo(): ?array
    {
        if (! self::can('SpeedTracker.AppInfo')) {
            return null;
        }

        $result = self::decode(nativephp_call('SpeedTracker.AppInfo', '{}'));

        if (! is_array($result) || ! is_string($result['version'] ?? null)) {
            return null;
        }

        return [
            'version' => $result['version'],
            'code' => (int) ($result['code'] ?? 0),
            'debug' => (bool) ($result['debug'] ?? false),
        ];
    }

    public static function acknowledgeNativeTrip(string $id): void
    {
        if (self::can('SpeedTracker.AcknowledgeNativeTrip')) {
            nativephp_call('SpeedTracker.AcknowledgeNativeTrip', json_encode(['id' => $id]));
        }
    }

    /**
     * Turn driving detection on or off. With $requestPermissions, missing
     * permissions are asked for.
     *
     * @param  'ask'|'auto'  $mode  ask with a notification, or start/stop by itself
     * @return 'enabled'|'disabled'|'permission_required'|'background_location_required'|'unavailable'
     */
    public static function setAutoRecord(bool $enabled, bool $requestPermissions = true, string $mode = 'ask'): string
    {
        if (! self::can('SpeedTracker.SetAutoRecord')) {
            return $enabled ? 'unavailable' : 'disabled';
        }

        $result = self::decode(nativephp_call('SpeedTracker.SetAutoRecord', json_encode([
            'enabled' => $enabled,
            'request' => $requestPermissions,
            'mode' => $mode === 'auto' ? 'auto' : 'ask',
        ])));

        return in_array($result['status'] ?? null, ['enabled', 'disabled', 'permission_required', 'background_location_required'], true)
            ? $result['status']
            : 'unavailable';
    }

    /**
     * A bridge response as an array, unwrapping a `data` envelope.
     *
     * @return array<string, mixed>|null
     */
    private static function decode(mixed $raw): ?array
    {
        $result = is_string($raw) ? json_decode($raw, true) : $raw;

        if (is_array($result) && is_array($result['data'] ?? null)) {
            return $result['data'];
        }

        return is_array($result) ? $result : null;
    }

    /**
     * Whether this build contains a bridge function.
     */
    private static function can(string $function): bool
    {
        return function_exists('nativephp_call')
            && (! function_exists('nativephp_can') || nativephp_can($function));
    }
}
