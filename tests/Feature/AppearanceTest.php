<?php

use App\Models\Setting;
use App\NativeComponents\Settings;
use App\Support\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;
use Native\Mobile\UI\Theme;

uses(RefreshDatabase::class);

beforeEach(function () {
    Native::fakeBridge();
});

it('forces dark mode by using the dark tokens for both appearances', function () {
    $tokens = Appearance::tokens('dark', 'teal');

    expect($tokens['light'])->toBe($tokens['dark'])
        ->and($tokens['light']['background'])->toBe(Theme::all()['dark']['background']);
});

it('forces light mode by using the light tokens for both appearances', function () {
    $tokens = Appearance::tokens('light', 'teal');

    expect($tokens['dark'])->toBe($tokens['light'])
        ->and($tokens['dark']['background'])->toBe(Theme::all()['light']['background']);
});

it('follows the system with the accent applied to each appearance', function () {
    $tokens = Appearance::tokens('system', 'violet');

    expect($tokens['light']['primary'])->toBe('#6D28D9')
        ->and($tokens['dark']['primary'])->toBe('#A78BFA')
        ->and($tokens['light']['background'])->not->toBe($tokens['dark']['background']);
});

it('saves and applies the theme from settings', function () {
    Native::test(Settings::class)
        ->tap('Dark')
        ->call('setAccent', 'blue')
        ->assertSee('Unsaved changes')
        ->tap('Save');

    expect(Setting::current())
        ->theme_mode->toBe('dark')
        ->accent->toBe('blue')
        ->and(Theme::all()['light']['primary'])->toBe('#60A5FA'); // dark blue used in both appearances

    Appearance::apply('system', 'teal');
});
