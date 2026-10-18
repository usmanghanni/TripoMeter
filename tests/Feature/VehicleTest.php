<?php

use App\Models\Expense;
use App\Models\FuelFill;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\Vehicle;
use App\NativeComponents\FuelLog;
use App\NativeComponents\Speedometer;
use App\NativeComponents\TripList;
use App\Support\OilChange;
use App\Support\TripBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
    $this->car = Vehicle::current();
    $this->bike = Vehicle::factory()->create(['name' => 'CD 70', 'oil_change_interval_km' => 1000]);
});

it('starts with My car as the current vehicle', function () {
    expect($this->car->name)->toBe('My car')
        ->and(Setting::current()->active_vehicle_id)->toBe($this->car->id);
});

it('records new trips, fill-ups and expenses for the current vehicle', function () {
    $this->bike->makeCurrent();

    Native::test(Speedometer::class)->tap('Start trip');

    expect(Trip::sole()->vehicle_id)->toBe($this->bike->id)
        ->and(FuelFill::factory()->create()->vehicle_id)->toBe($this->bike->id)
        ->and(Expense::factory()->create()->vehicle_id)->toBe($this->bike->id);
});

it('shows only the current vehicle\'s trips and fill-ups, with a switcher', function () {
    Trip::factory()->create(['title' => 'Car commute', 'vehicle_id' => $this->car->id, 'ended_at' => now()]);
    Trip::factory()->create(['title' => 'Bike errand', 'vehicle_id' => $this->bike->id, 'ended_at' => now()]);
    FuelFill::factory()->create(['vehicle_id' => $this->car->id, 'odometer_km' => 40_000]);
    FuelFill::factory()->create(['vehicle_id' => $this->bike->id, 'odometer_km' => 9_000]);

    Native::test(TripList::class)
        ->assertSee('Car commute')
        ->assertDontSee('Bike errand')
        ->tap('CD 70')
        ->assertSee('Bike errand')
        ->assertDontSee('Car commute');

    expect(Vehicle::current()->is($this->bike))->toBeTrue();

    Native::test(FuelLog::class)->assertSee('9,000 km')->assertDontSee('40,000 km');
});

it('keeps oil change intervals per vehicle', function () {
    Expense::factory()->oilChange()->create(['vehicle_id' => $this->car->id, 'odometer_km' => 10_000]);
    Expense::factory()->oilChange()->create(['vehicle_id' => $this->bike->id, 'odometer_km' => 5_000]);

    expect(OilChange::status()['due_km'])->toBe(15_000.0);

    $this->bike->makeCurrent();

    expect(OilChange::status()['due_km'])->toBe(6_000.0);
});

it('adds a vehicle and makes it current', function () {
    Native::visit('/vehicles/add')
        ->set('name', 'Civic')
        ->set('tankCapacity', '47')
        ->set('oilIntervalKm', '7500')
        ->tap('Save');

    $civic = Vehicle::firstWhere('name', 'Civic');

    expect($civic)
        ->tank_capacity_l->toBe(47.0)
        ->oil_change_interval_km->toBe(7500)
        ->and(Vehicle::current()->is($civic))->toBeTrue();
});

it('validates the vehicle form', function () {
    Native::visit('/vehicles/add')
        ->set('name', '')
        ->set('tankCapacity', 'lots')
        ->set('oilIntervalKm', '5k')
        ->tap('Save')
        ->assertSee('The name field is required.')
        ->assertSee('The tank size field must be a number.')
        ->assertSee('The oil change distance field must be an integer.');
});

it('lists vehicles and switches to one from its screen', function () {
    Native::visit('/vehicles')
        ->assertSee('My car')
        ->assertSee('CD 70')
        ->assertSee('Current');

    Native::visit("/vehicles/{$this->bike->id}")
        ->tap('Use this vehicle');

    expect(Vehicle::current()->is($this->bike))->toBeTrue();
});

it('deletes a vehicle with its records after confirming, but never the last one', function () {
    Trip::factory()->create(['vehicle_id' => $this->bike->id]);
    FuelFill::factory()->create(['vehicle_id' => $this->bike->id]);

    Native::visit("/vehicles/{$this->bike->id}")
        ->tap('Delete vehicle')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-vehicle']);

    expect(Vehicle::count())->toBe(1)
        ->and(Trip::count())->toBe(0)
        ->and(FuelFill::count())->toBe(0);

    Native::visit("/vehicles/{$this->car->id}")->tap('Delete vehicle');

    expect(Vehicle::count())->toBe(1);
});

it('backs up and restores records with their vehicles', function () {
    $this->bike->update(['tank_capacity_l' => 9]);
    $trip = Trip::factory()->create(['vehicle_id' => $this->bike->id, 'started_at' => '2026-10-01 08:00:00', 'ended_at' => '2026-10-01 08:30:00']);
    FuelFill::factory()->create(['vehicle_id' => $this->bike->id]);

    $backup = TripBackup::read(TripBackup::write(TripBackup::build([$trip->id], includeFuel: true)));

    Trip::query()->delete();
    FuelFill::query()->delete();
    $this->bike->delete();

    TripBackup::import($backup, [0], includeFuel: true);

    $restored = Vehicle::firstWhere('name', 'CD 70');

    expect($restored)->not->toBeNull()
        ->and($restored->tank_capacity_l)->toBe(9.0)
        ->and(Trip::sole()->vehicle_id)->toBe($restored->id)
        ->and(FuelFill::sole()->vehicle_id)->toBe($restored->id);
});
