<?php

namespace App\Support;

use App\Models\Setting;
use Native\Mobile\UI\Theme;
use Throwable;

/**
 * Applies the chosen theme: light, dark or the phone's setting, plus an
 * accent colour.
 *
 * Renderers pick the light or dark token set from the system appearance, so
 * a forced mode makes both sets the same: "dark" copies the dark tokens over
 * the light ones and "light" the reverse.
 */
class Appearance
{
    /**
     * @var array<string, string>
     */
    public const MODES = ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'];

    /**
     * Primary colour per accent, for light and dark surfaces (each pair keeps
     * WCAG AA contrast with its on-primary colour).
     *
     * @var array<string, array{label: string, light: array{primary: string, on-primary: string}, dark: array{primary: string, on-primary: string}}>
     */
    public const ACCENTS = [
        'teal' => ['label' => 'Teal', 'light' => ['primary' => '#0F766E', 'on-primary' => '#FFFFFF'], 'dark' => ['primary' => '#14B8A6', 'on-primary' => '#FFFFFF']],
        'blue' => ['label' => 'Blue', 'light' => ['primary' => '#1D4ED8', 'on-primary' => '#FFFFFF'], 'dark' => ['primary' => '#60A5FA', 'on-primary' => '#0F172A']],
        'violet' => ['label' => 'Violet', 'light' => ['primary' => '#6D28D9', 'on-primary' => '#FFFFFF'], 'dark' => ['primary' => '#A78BFA', 'on-primary' => '#0F172A']],
        'orange' => ['label' => 'Orange', 'light' => ['primary' => '#C2410C', 'on-primary' => '#FFFFFF'], 'dark' => ['primary' => '#FB923C', 'on-primary' => '#0F172A']],
        'rose' => ['label' => 'Rose', 'light' => ['primary' => '#BE123C', 'on-primary' => '#FFFFFF'], 'dark' => ['primary' => '#FB7185', 'on-primary' => '#0F172A']],
        'green' => ['label' => 'Green', 'light' => ['primary' => '#15803D', 'on-primary' => '#FFFFFF'], 'dark' => ['primary' => '#4ADE80', 'on-primary' => '#0F172A']],
    ];

    /**
     * The theme from config/native-ui.php, captured before any change.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $baseTokens = null;

    /**
     * Apply the saved choice at app start. Defaults are left alone so a normal
     * launch makes no extra bridge call; a database that isn't migrated yet
     * (first launch, tests) is ignored.
     */
    public static function applySaved(): void
    {
        try {
            $setting = Setting::current();
        } catch (Throwable) {
            return;
        }

        $mode = $setting->theme_mode ?? 'system';
        $accent = $setting->accent ?? 'teal';

        if ($mode !== 'system' || $accent !== 'teal') {
            self::apply($mode, $accent);
        }
    }

    public static function apply(string $mode, string $accent): void
    {
        Theme::load(self::tokens($mode, $accent));
    }

    /**
     * @return array<string, mixed>
     */
    public static function tokens(string $mode, string $accent): array
    {
        self::$baseTokens ??= Theme::all();

        $tokens = self::$baseTokens;
        unset($tokens['fonts']); // kept by Theme itself and re-added on push

        $palette = self::ACCENTS[$accent] ?? self::ACCENTS['teal'];
        $tokens['light'] = [...$tokens['light'] ?? [], ...$palette['light']];
        $tokens['dark'] = [...$tokens['dark'] ?? [], ...$palette['dark']];

        if ($mode === 'dark') {
            $tokens['light'] = $tokens['dark'];
        } elseif ($mode === 'light') {
            $tokens['dark'] = $tokens['light'];
        }

        return $tokens;
    }
}
