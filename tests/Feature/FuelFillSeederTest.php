<?php

use App\Models\FuelFill;
use Database\Seeders\FuelFillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the fuel history once without duplicating existing fills', function () {
    FuelFill::factory()->create(['odometer_km' => 47895, 'liters' => 26.47]);

    (new FuelFillSeeder)->run();
    (new FuelFillSeeder)->run();

    expect(FuelFill::count())->toBe(65)
        ->and(FuelFill::where('odometer_km', 47895)->count())->toBe(1);

    $last = FuelFill::orderByDesc('odometer_km')->first();
    expect($last->filled_on->toDateString())->toBe('2026-10-14')
        ->and($last->liters)->toBe(5.33)
        ->and($last->total_cost)->toBe(2100.61);
});

it('gives a realistic fuel average from the history', function () {
    (new FuelFillSeeder)->run();

    // 13,505 km over 821.86 L after the first fill.
    expect(FuelFill::economyReport()['km_per_liter'])->toEqualWithDelta(16.43, 0.01);
});
