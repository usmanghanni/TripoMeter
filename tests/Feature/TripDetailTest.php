<?php

use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
    $this->trip = Trip::factory()->create([
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'distance_m' => 42_000,
        'max_speed_mps' => 25,
    ]);
});

it('shows trip stats', function () {
    Native::visit("/trips/{$this->trip->id}")
        ->assertSee('42.00 km')
        ->assertSee('01:00:00')
        ->assertSee('42 km/h') // average
        ->assertSee('90 km/h'); // top speed
});

it('renames a trip and adds notes', function () {
    Native::visit("/trips/{$this->trip->id}")
        ->input("__syncProperty('title')", 'Office commute')
        ->input("__syncProperty('notes')", 'Heavy traffic')
        ->tap('Save');

    expect($this->trip->fresh())
        ->title->toBe('Office commute')
        ->notes->toBe('Heavy traffic');

    $this->bridge->assertCalled('Dialog.Toast');
});

it('deletes a trip after confirming', function () {
    $screen = Native::visit("/trips/{$this->trip->id}")->tap('Delete trip');

    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-trip']);

    expect(Trip::count())->toBe(0);
});

it('lists trips by title and opens their details', function () {
    $this->trip->update(['title' => 'Office commute']);

    Native::visit('/trips')
        ->assertSee('Office commute')
        ->call('openTrip', $this->trip->id)
        ->assertNavigatedTo("/trips/{$this->trip->id}");
});
