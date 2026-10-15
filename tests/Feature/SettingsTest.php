<?php

use App\Models\Setting;
use App\NativeComponents\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('is seeded with metric defaults', function () {
    expect(Setting::count())->toBe(1)
        ->and(Setting::current()->unit)->toBe('kmh')
        ->and(Setting::current()->speed_limit_kmh)->toBe(0);
});

it('saves unit and speed limit', function () {
    Native::test(Settings::class)
        ->assertSee('Off')
        ->set('unitIndex', 1)
        ->set('speedLimit', 65.0)
        ->assertSee('Alert above 65 mph');

    expect(Setting::current())
        ->unit->toBe('mph')
        ->speed_limit_kmh->toBe(105); // 65 mph
});

it('converts speed and distance per unit', function () {
    $setting = new Setting(['unit' => 'mph', 'speed_limit_kmh' => 97]); // ~60 mph

    expect($setting->speed(10))->toEqualWithDelta(22.37, 0.01)
        ->and($setting->distance(1609.344))->toEqualWithDelta(1.0, 0.0001)
        ->and($setting->isOverLimit(26.0))->toBeFalse() // 93.6 km/h
        ->and($setting->isOverLimit(27.0))->toBeTrue() // 97.2 km/h
        ->and($setting->fromKmh(97))->toBe(60)
        ->and($setting->toKmh(60))->toBe(97);
});

it('saves the road limit alert toggle', function () {
    Native::test(Settings::class)->set('roadLimitAlerts', false);

    expect(Setting::current()->road_limit_alerts)->toBeFalse();
});

it('saves the alert type', function () {
    Native::test(Settings::class)->set('alertModeIndex', 2);

    expect(Setting::current()->alert_mode)->toBe('both');
});

it('converts the speed limit when switching units instead of keeping the number', function () {
    Setting::current()->update(['unit' => 'kmh', 'speed_limit_kmh' => 120]);
    $bridge = Native::fakeBridge();

    $screen = Native::test(Settings::class)
        ->assertSee('Alert above 120 km/h')
        ->set('unitIndex', 1)
        ->assertSet('speedLimit', 75.0)
        ->assertSee('Alert above 75 mph');

    expect(Setting::current()->speed_limit_kmh)->toBe(120);
    $bridge->assertCalled('SpeedTracker.SetUnit', fn (array $params) => $params['unit'] === 'mph');

    // Switching back is lossless because the stored value never changed.
    $screen->set('unitIndex', 0)->assertSee('Alert above 120 km/h');
});
