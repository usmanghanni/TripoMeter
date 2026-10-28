<?php

namespace App\Support;

/**
 * Third-party software bundled in the app, for the About screen. PHP
 * packages come from composer.lock via `php artisan licenses:generate`
 * (stored in resources/licenses.json, which ships in the app; composer.lock
 * doesn't); native Android libraries are listed here.
 */
class OpenSourceLicenses
{
    public const PATH = 'resources/licenses.json';

    /**
     * @var list<array{name: string, license: string}>
     */
    public const NATIVE = [
        ['name' => 'Android Jetpack (AndroidX)', 'license' => 'Apache-2.0'],
        ['name' => 'Kotlin standard library', 'license' => 'Apache-2.0'],
        ['name' => 'Google Play services Location', 'license' => 'Android Software Development Kit License'],
        ['name' => 'PHP runtime', 'license' => 'PHP-3.01'],
        ['name' => 'Road speed limits: OpenStreetMap contributors', 'license' => 'ODbL-1.0'],
    ];

    /**
     * Non-dev Composer packages from a composer.lock file, by name.
     *
     * @return list<array{name: string, version: string, license: string}>
     */
    public static function fromComposerLock(string $lockPath): array
    {
        $lock = json_decode((string) file_get_contents($lockPath), true);

        return collect($lock['packages'] ?? [])
            ->map(fn (array $package): array => [
                'name' => $package['name'],
                'version' => ltrim((string) ($package['version'] ?? ''), 'v'),
                'license' => implode(' or ', $package['license'] ?? ['Unknown']),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Everything to credit: native libraries, then PHP packages.
     *
     * @return list<array{name: string, version?: string, license: string}>
     */
    public static function all(): array
    {
        $path = base_path(self::PATH);
        $packages = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        return [...self::NATIVE, ...(is_array($packages) ? $packages : [])];
    }
}
