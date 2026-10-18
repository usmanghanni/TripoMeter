<?php

use App\Models\Setting;
use App\NativeComponents\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
});

it('is seeded with metric defaults', function () {
    expect(Setting::count())->toBe(1)
        ->and(Setting::current()->unit)->toBe('kmh')
        ->and(Setting::current()->speed_limit_kmh)->toBe(0);
});

/*
 * These drive the real native events (the same wire events the device sends
 * for each control), not ->set(), so they cover the native:model bindings.
 */
it('saves every setting from the real controls when Save is tapped', function () {
    $screen = Native::test(Settings::class)
        ->tap('mph')
        ->tap('Both')
        ->toggle("__syncProperty('roadLimitAlerts')", false)
        ->slide("__syncProperty('speedLimit')", 65.0)
        ->assertSee('Alert above 65 mph')
        ->assertSee('Unsaved changes');

    expect(Setting::current()->unit)->toBe('kmh'); // nothing written before Save

    $screen->tap('Save')->assertDontSee('Unsaved changes');

    expect(Setting::current())
        ->unit->toBe('mph')
        ->alert_mode->toBe('both')
        ->road_limit_alerts->toBeFalse()
        ->speed_limit_kmh->toBe(105); // 65 mph

    $this->bridge->assertCalled('SpeedTracker.SetUnit', fn (array $params) => $params['unit'] === 'mph');
    $this->bridge->assertCalled('Dialog.Toast');
});

it('shows saved values when reopened', function () {
    Setting::current()->update(['unit' => 'mph', 'speed_limit_kmh' => 97, 'alert_mode' => 'sound', 'road_limit_alerts' => false]);

    Native::test(Settings::class)
        ->assertSet('unit', 'mph')
        ->assertSet('alertMode', 'sound')
        ->assertSet('roadLimitAlerts', false)
        ->assertSee('Alert above 60 mph');
});

it('converts the speed limit when switching units instead of keeping the number', function () {
    Setting::current()->update(['unit' => 'kmh', 'speed_limit_kmh' => 120]);

    $screen = Native::test(Settings::class)
        ->assertSee('Alert above 120 km/h')
        ->tap('mph')
        ->assertSet('speedLimit', 75.0)
        ->assertSee('Alert above 75 mph');

    // Switching back is lossless because the km/h value never changed.
    $screen->tap('km/h')->assertSee('Alert above 120 km/h')
        ->tap('Save');

    expect(Setting::current()->speed_limit_kmh)->toBe(120);
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

it('saves alert volume, tank size and refuel reminders', function () {
    Native::test(Settings::class)
        ->tap('Sound')
        ->slide("__syncProperty('alertVolume')", 60.0)
        ->assertSee('Alert volume · 60%')
        ->tap('Test')
        ->toggle("__syncProperty('refuelReminders')", false)
        ->input("__syncProperty('tankCapacity')", '42.5')
        ->tap('Save');

    expect(Setting::current())
        ->alert_volume->toBe(60)
        ->tank_capacity_l->toBe(42.5)
        ->refuel_reminders->toBeFalse();

    $this->bridge->assertCalled('SpeedTracker.PlayAlert', fn (array $params) => $params['volume'] === 60);
});

it('rejects an invalid tank size', function () {
    Native::test(Settings::class)
        ->set('tankCapacity', 'lots')
        ->tap('Save')
        ->assertSee('Enter the tank size in litres, or leave it empty.');

    expect(Setting::current()->tank_capacity_l)->toBeNull();
});
