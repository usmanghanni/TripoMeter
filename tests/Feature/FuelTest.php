<?php

use App\Models\FuelFill;
use App\NativeComponents\FuelFillForm;
use App\NativeComponents\FuelLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Events\Alert\ButtonPressed;
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
    Native::test(FuelFillForm::class)
        ->set('liters', '0')
        ->tap('Save')
        ->assertSee('The odometer field is required.')
        ->assertSee('The liters field must be greater than 0.');

    expect(FuelFill::count())->toBe(0);

    Native::test(FuelFillForm::class)
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

it('edits an existing fill-up', function () {
    $fill = FuelFill::factory()->create(['odometer_km' => 50000, 'liters' => 20, 'total_cost' => 5000, 'is_full_tank' => true]);

    Native::visit("/fuel/{$fill->id}")
        ->assertSet('odometer', '50000')
        ->assertSet('liters', '20')
        ->assertSee('Delete fill-up')
        ->input("__syncProperty('liters')", '21.5')
        ->tap('Save');

    expect($fill->fresh()->liters)->toBe(21.5)
        ->and(FuelFill::count())->toBe(1);
});

it('deletes a fill-up from the edit screen after confirming', function () {
    $fill = FuelFill::factory()->create();
    $bridge = Native::fakeBridge();

    $screen = Native::visit("/fuel/{$fill->id}")->tap('Delete fill-up');
    $bridge->assertCalled('Dialog.Alert');

    $screen->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-fuel-fill']);
    expect(FuelFill::count())->toBe(1);

    // Each alert handles one press; ask again and confirm.
    $screen->tap('Delete fill-up')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-fuel-fill']);
    expect(FuelFill::count())->toBe(0);
});

it('shows price per litre, latest and average price', function () {
    FuelFill::factory()->create(['filled_on' => '2026-09-01', 'odometer_km' => 1000, 'liters' => 20, 'total_cost' => 6000]); // 300/L
    FuelFill::factory()->create(['filled_on' => '2026-10-01', 'odometer_km' => 1400, 'liters' => 10, 'total_cost' => 4000]); // 400/L

    $report = FuelFill::economyReport();

    expect($report['latest_price'])->toEqualWithDelta(400.0, 0.001)
        ->and($report['average_price'])->toEqualWithDelta(333.33, 0.01); // 10000 / 30 L

    Native::test(FuelLog::class)
        ->assertSee('Latest price')
        ->assertSee('400.00')
        ->assertSee('333.33')
        ->assertSee('@ 300.00/L')
        ->assertSee('Price per litre, last 12 months');
});

it('averages price per month weighted by volume', function () {
    $this->travelTo(now()->setDate(2026, 10, 15));
    FuelFill::factory()->create(['filled_on' => '2026-10-02', 'odometer_km' => 1000, 'liters' => 30, 'total_cost' => 9000]);
    FuelFill::factory()->create(['filled_on' => '2026-10-09', 'odometer_km' => 1300, 'liters' => 10, 'total_cost' => 4000]);
    FuelFill::factory()->create(['filled_on' => '2026-08-20', 'odometer_km' => 900, 'liters' => 10, 'total_cost' => 2500]);

    $months = FuelFill::monthlyPrices();

    expect($months)->toHaveCount(12)
        ->and(end($months))->toBe(['label' => 'Oct', 'price' => 325.0]) // 13000 / 40 L
        ->and($months[9])->toBe(['label' => 'Aug', 'price' => 250.0])
        ->and($months[10]['price'])->toBeNull();
});

it('shows the price per litre while entering a fill-up', function () {
    Native::test(FuelFillForm::class)
        ->input("__syncProperty('liters')", '20')
        ->input("__syncProperty('totalCost')", '8000')
        ->assertSee('Price per litre: 400.00');
});
