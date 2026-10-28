<?php

namespace App\Support;

use App\SpeedTracker\SpeedTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Compares the installed build with the latest GitHub release. Needs the
 * repository to be public: the app carries no GitHub token.
 *
 * The latest release is cached and fetched at most once a day (HTTP runs in
 * the CheckForUpdate background task); "Later" hides one version.
 */
class UpdateCheck
{
    public const REPOSITORY = 'usmanghanni/TripoMeter';

    public const ENDPOINT = 'https://api.github.com/repos/'.self::REPOSITORY.'/releases/latest';

    public const RELEASES_PAGE = 'https://github.com/'.self::REPOSITORY.'/releases';

    private const CACHE_KEY = 'update_check.latest';

    private const DISMISSED_KEY = 'update_check.dismissed';

    private const CHECK_EVERY_SECONDS = 86_400;

    /**
     * Fetch the latest release from GitHub and cache it. Null when offline,
     * rate limited, or the repository isn't public.
     *
     * @return array{version: string, page: string, notes: string, assets: list<array{name: string, url: string}>}|null
     */
    public static function fetchLatest(): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withUserAgent('Tripometer')
                ->acceptJson()
                ->get(self::ENDPOINT);
        } catch (Throwable) {
            return null;
        }

        $release = $response->successful() ? self::parseRelease($response->json()) : null;

        // Failures are remembered too, so a private repo or no network
        // doesn't mean a request on every launch.
        Cache::put(self::CACHE_KEY, ['checked_at' => time(), 'release' => $release], now()->addDays(30));

        return $release;
    }

    public static function isDue(): bool
    {
        $cached = Cache::get(self::CACHE_KEY);

        return ! is_array($cached) || time() - ($cached['checked_at'] ?? 0) >= self::CHECK_EVERY_SECONDS;
    }

    /**
     * The update to offer from the cached release, if newer than the
     * installed build and not dismissed. No network.
     *
     * @param  bool  $includeDismissed  for "Check now", which should always answer
     * @return array{version: string, installed: string, url: string, page: string, notes: string}|null
     */
    public static function available(bool $includeDismissed = false): ?array
    {
        $installed = SpeedTracker::appInfo();
        $release = Cache::get(self::CACHE_KEY)['release'] ?? null;

        if (! $installed || ! is_array($release) || ! self::isNewer($release['version'], $installed['version'])) {
            return null;
        }

        if (! $includeDismissed && Cache::get(self::DISMISSED_KEY) === $release['version']) {
            return null;
        }

        return [
            'version' => $release['version'],
            'installed' => $installed['version'],
            'url' => self::apkUrl($release, $installed['debug']) ?? $release['page'],
            'page' => $release['page'],
            'notes' => $release['notes'],
        ];
    }

    public static function dismiss(string $version): void
    {
        Cache::forever(self::DISMISSED_KEY, $version);
    }

    /**
     * Whether $latest is a newer version than $installed. Builds that aren't
     * versioned releases ("DEBUG", "0.0.0-abc1234") never get offers.
     */
    public static function isNewer(string $latest, string $installed): bool
    {
        $pattern = '/^\d+\.\d+(\.\d+)?$/';

        return preg_match($pattern, $latest) === 1
            && preg_match($pattern, $installed) === 1
            && version_compare($latest, $installed, '>');
    }

    /**
     * @param  array<string, mixed>|null  $json  GitHub's release payload
     * @return array{version: string, page: string, notes: string, assets: list<array{name: string, url: string}>}|null
     */
    public static function parseRelease(?array $json): ?array
    {
        if (! is_string($json['tag_name'] ?? null)) {
            return null;
        }

        return [
            'version' => ltrim($json['tag_name'], 'vV'),
            'page' => (string) ($json['html_url'] ?? self::RELEASES_PAGE),
            'notes' => mb_strimwidth(trim((string) ($json['body'] ?? '')), 0, 600, '…'),
            'assets' => collect($json['assets'] ?? [])
                ->filter(fn (mixed $asset): bool => is_array($asset) && str_ends_with((string) ($asset['name'] ?? ''), '.apk'))
                ->map(fn (array $asset): array => ['name' => $asset['name'], 'url' => (string) $asset['browser_download_url']])
                ->values()
                ->all(),
        ];
    }

    /**
     * The release APK for a release build, the debug one for a debug build.
     *
     * @param  array{assets: list<array{name: string, url: string}>}  $release
     */
    private static function apkUrl(array $release, bool $debug): ?string
    {
        return collect($release['assets'])
            ->first(fn (array $asset): bool => str_ends_with($asset['name'], '-debug.apk') === $debug)['url'] ?? null;
    }
}
