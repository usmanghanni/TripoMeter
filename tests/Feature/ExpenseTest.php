<?php

use App\Models\Expense;
use App\Models\FuelFill;
use App\Models\Setting;
use App\Models\Trip;
use App\NativeComponents\ExpenseForm;
use App\NativeComponents\ExpenseLog;
use App\NativeComponents\FuelLog;
use App\NativeComponents\Settings;
use App\NativeComponents\Speedometer;
use App\Support\Odometer;
use App\Support\OilChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
    $this->travelTo('2026-10-18 12:00:00');
});

it('estimates the odometer from the latest reading plus recorded trips', function () {
    FuelFill::factory()->create(['filled_on' => '2026-10-01', 'odometer_km' => 12_000]);
    Expense::factory()->create(['spent_on' => '2026-10-05', 'odometer_km' => 12_150]);
    Trip::factory()->create(['started_at' => '2026-10-04 09:00:00', 'ended_at' => '2026-10-04 10:00:00', 'distance_m' => 50_000]); // before the reading
    Trip::factory()->create(['started_at' => '2026-10-10 09:00:00', 'ended_at' => '2026-10-10 10:00:00', 'distance_m' => 80_000]);

    expect(Odometer::current())
        ->km->toEqualWithDelta(12_230.0, 0.01)
        ->reading_km->toBe(12_150.0)
        ->driven_since_km->toEqualWithDelta(80.0, 0.01);
});

it('works out the next oil change by distance or time, whichever is first', function () {
    Setting::current()->update(['oil_change_interval_km' => 5000, 'oil_change_interval_months' => 6]);
    Expense::factory()->oilChange()->create(['spent_on' => '2026-08-18', 'odometer_km' => 10_000]);
    FuelFill::factory()->create(['filled_on' => '2026-10-15', 'odometer_km' => 14_000]);

    $status = OilChange::status();

    expect($status)
        ->due_km->toBe(15_000.0)
        ->remaining_km->toEqualWithDelta(1000.0, 0.01)
        ->due_on->toDateString()->toBe('2027-02-18')
        ->is_due_soon->toBeFalse()
        ->is_overdue->toBeFalse()
        ->fraction_left->toEqualWithDelta(0.2, 0.001)
        ->and(OilChange::summary($status))->toBe('Due in 1,000 km · due by 18 Feb 2027');
});

it('flags an overdue oil change and reminds once', function () {
    Expense::factory()->oilChange()->create(['spent_on' => '2026-08-18', 'odometer_km' => 10_000]);
    FuelFill::factory()->create(['filled_on' => '2026-10-15', 'odometer_km' => 15_120]);

    expect(OilChange::status())->is_overdue->toBeTrue()
        ->and(OilChange::summary(OilChange::status()))->toStartWith('120 km overdue')
        ->and(OilChange::remindIfDue())->toBeTrue()
        ->and(OilChange::remindIfDue())->toBeFalse();

    $this->bridge->assertCalled('SpeedTracker.Notify', fn (array $params) => $params['title'] === 'Oil change due');

    Native::test(Speedometer::class)->assertSee('Oil change 120 km overdue');
    Native::test(FuelLog::class)->assertSee('Oil change 120 km overdue');
});

it('has no oil change status until one is logged', function () {
    expect(OilChange::status())->toBeNull();

    Native::test(ExpenseLog::class)
        ->assertSee('Track oil changes')
        ->call('addOilChange')
        ->assertNavigatedTo('/expenses/add/oil_change');
});

it('adds an oil change, requiring the odometer', function () {
    Native::visit('/expenses/add/oil_change')
        ->assertSet('category', 'oil_change')
        ->set('amount', '4500')
        ->tap('Save')
        ->assertSee('Enter the odometer so the next oil change can be worked out.')
        ->set('odometer', '20500')
        ->set('title', 'Synthetic 5W-30')
        ->tap('Save');

    expect(Expense::sole())
        ->category->toBe('oil_change')
        ->amount->toBe(4500.0)
        ->odometer_km->toBe(20_500.0)
        ->title->toBe('Synthetic 5W-30');
});

it('adds other expenses without an odometer and edits them', function () {
    Native::test(ExpenseForm::class)
        ->tap('Insurance')
        ->set('amount', '32000')
        ->tap('Save');

    $expense = Expense::sole();

    expect($expense)->category->toBe('insurance')->odometer_km->toBeNull();

    Native::visit("/expenses/{$expense->id}")
        ->assertSet('amount', '32000')
        ->set('amount', '30000')
        ->tap('Save');

    expect($expense->fresh()->amount)->toBe(30_000.0);
});

it('lists expenses with this year\'s totals by category', function () {
    Expense::factory()->create(['category' => 'service', 'amount' => 8000, 'spent_on' => '2026-03-01']);
    Expense::factory()->create(['category' => 'service', 'amount' => 2000, 'spent_on' => '2026-09-01']);
    Expense::factory()->create(['category' => 'tyres', 'amount' => 25000, 'spent_on' => '2026-05-01']);
    Expense::factory()->create(['category' => 'wash', 'amount' => 500, 'spent_on' => '2025-12-01']); // last year

    expect(Expense::totalsByCategory(today()->startOfYear()))->toBe(['tyres' => 25000.0, 'service' => 10000.0]);

    Native::test(ExpenseLog::class)
        ->assertSee('Spent in 2026')
        ->assertSee('35,000.00')
        ->assertSee('Tyres')
        ->assertSee('Wash');
});

it('opens expenses from the fuel screen', function () {
    Native::test(FuelLog::class)->tap('Expenses')->assertNavigatedTo('/expenses');
});

it('saves the oil change interval in settings', function () {
    Native::test(Settings::class)
        ->input("__syncProperty('oilIntervalKm')", '7500')
        ->input("__syncProperty('oilIntervalMonths')", '12')
        ->tap('Save');

    expect(Setting::current())
        ->oil_change_interval_km->toBe(7500)
        ->oil_change_interval_months->toBe(12);
});

it('rejects an invalid oil change interval', function () {
    Native::test(Settings::class)
        ->set('oilIntervalKm', '5k')
        ->tap('Save')
        ->assertSee('Enter whole numbers');

    expect(Setting::current()->oil_change_interval_km)->toBe(5000);
});
