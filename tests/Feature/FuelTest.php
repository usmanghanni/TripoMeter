<?php

use App\Models\FuelFill;
use App\NativeComponents\AddFuelFill;
use App\NativeComponents\FuelLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('calculates fuel average full tank to full tank', function () {
    FuelFill::factory()->create(['odometer_km' => 1000, 'liters' => 30, 'total_cost' => 3000]); // baseline, ignored
    FuelFill::factory()->create(['odometer_km' => 1200, 'liters' => 5, 'total_cost' => 500, 'is_full_tank' => false]);
    $full = FuelFill::factory()->create(['odometer_km' => 1500, 'liters' => 20, 'total_cost' => 2000]);

    $report = FuelFill::economyReport();

    // 500 km on 25 L (partial + full), costing 2500
    expect($report['km_per_liter'])->toEqualWithDelta(20.0, 0.001)
        ->and($report['liters_per_100km'])->toEqualWithDelta(5.0, 0.001)
        ->and($report['cost_per_km'])->toEqualWithDelta(5.0, 0.001)
        ->and($report['total_cost'])->toEqualWithDelta(5500.0, 0.001)
        ->and($report['by_fill'])->toBe([$full->id => 20.0]);
});

it('has no average until two full fills exist', function () {
    FuelFill::factory()->create(['odometer_km' => 1000]);

    expect(FuelFill::economyReport()['km_per_liter'])->toBeNull();

    Native::test(FuelLog::class)->assertSee('--')->assertSee('Add fill-up');
});

it('adds a fill-up with validation', function () {
    Native::test(AddFuelFill::class)
        ->set('liters', '0')
        ->tap('Save')
        ->assertSee('The odometer field is required.')
        ->assertSee('The liters field must be greater than 0.');

    expect(FuelFill::count())->toBe(0);

    Native::test(AddFuelFill::class)
        ->set('odometer', '12345.6')
        ->set('liters', '32.5')
        ->set('totalCost', '3400')
        ->set('isFullTank', false)
        ->tap('Save');

    expect(FuelFill::sole())
        ->odometer_km->toBe(12345.6)
        ->liters->toBe(32.5)
        ->is_full_tank->toBeFalse()
        ->filled_on->toDateString()->toBe(today()->toDateString());
});

it('deletes a fill-up', function () {
    $fill = FuelFill::factory()->create();

    Native::test(FuelLog::class)->call('deleteFill', $fill->id);

    expect(FuelFill::count())->toBe(0);
});
