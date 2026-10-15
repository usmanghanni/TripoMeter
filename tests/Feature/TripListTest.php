<?php

use App\Models\Trip;
use App\NativeComponents\TripList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('lists finished trips and deletes them', function () {
    $finished = Trip::factory()->create(['ended_at' => now(), 'distance_m' => 12_340]);
    $active = Trip::factory()->create();

    Native::test(TripList::class)
        ->assertSee('12.34 km')
        ->call('deleteTrip', $finished->id)
        ->call('deleteTrip', $active->id)
        ->assertSee('No trips yet.');

    expect(Trip::find($finished->id))->toBeNull()
        ->and(Trip::find($active->id))->not->toBeNull();
});
